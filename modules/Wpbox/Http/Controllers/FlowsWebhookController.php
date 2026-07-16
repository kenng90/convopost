<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlows\WhatsappFlowDataExchangeResolver;
use App\Services\WhatsappFlows\WhatsappFlowScreenInitService;
use App\Traits\EnsuresOpenSsl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Wpbox\Traits\Contacts;

class FlowsWebhookController extends Controller
{
    use Contacts;
    use EnsuresOpenSsl;

    public function __construct(
        private readonly WhatsappFlowDataExchangeResolver $dataExchangeResolver,
        private readonly WhatsappFlowScreenInitService $screenInitService,
    ) {
    }

    /**
     * Handle GET — Meta webhook verification (hub challenge).
     */
    public function verify(Request $request, string $token): mixed
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('WhatsApp Flow webhook verification attempt', ['mode' => $mode]);

        $personalToken = PersonalAccessToken::findToken($verifyToken ?? $token);

        if (! $personalToken) {
            Log::warning('WhatsApp Flow webhook verification failed: invalid token');

            return response()->json(['error' => 'Invalid token'], 403);
        }

        if ($mode === 'subscribe') {
            Log::info('WhatsApp Flow webhook verified successfully');

            return response($challenge, 200);
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle POST — Meta sends encrypted data_exchange payloads here.
     *
     * Per Meta's official sample (encryption.js / server.js):
     * - Request body has: encrypted_aes_key, encrypted_flow_data, initial_vector
     * - Response must be the raw base64 encrypted string (NOT JSON-wrapped)
     * - Request signature must be validated via x-hub-signature-256 header
     */
    public function receive(Request $request, string $token): Response
    {
        Log::info('WhatsApp Flow webhook received', [
            'token' => substr($token, 0, 8).'...',
            'is_encrypted' => $request->has('encrypted_flow_data'),
        ]);

        // Resolve token → user → company (step by step with logs to diagnose crashes)
        Log::debug('WhatsApp Flow: looking up token');
        $personalToken = PersonalAccessToken::findToken($token);
        if (! $personalToken) {
            Log::warning('WhatsApp Flow webhook: invalid token');

            return response('Unauthorized', 401);
        }

        Log::debug('WhatsApp Flow: looking up user', ['tokenable_id' => $personalToken->tokenable_id]);
        $user = User::find($personalToken->tokenable_id);
        if (! $user) {
            Log::warning('WhatsApp Flow webhook: user not found');

            return response('User not found', 401);
        }

        Log::debug('WhatsApp Flow: looking up company', ['user_id' => $user->id]);
        $company = $this->resolveCompanyForFlowWebhook($user, $request);
        if (! $company) {
            Log::warning('WhatsApp Flow webhook: company not found for user', ['user_id' => $user->id]);

            return response('Company not found', 401);
        }

        $this->setWebhookCompanyContext($company);

        Log::debug('WhatsApp Flow: company found', ['company_id' => $company->id]);

        // Validate request signature (x-hub-signature-256) if APP_SECRET is configured
        if (! $this->isRequestSignatureValid($request, $company)) {
            Log::warning('WhatsApp Flow webhook: invalid request signature');

            return response('Invalid signature', 432);
        }

        // All Meta Flow requests are encrypted
        if ($request->has('encrypted_flow_data')) {
            return $this->handleEncryptedRequest($request, $company);
        }

        // Fallback: plain request (local testing only)
        return $this->handlePlainRequest($request);
    }

    /**
     * Decrypt Meta's payload, process it, re-encrypt and return the raw base64 string.
     *
     * Meta's official sample (server.js) does:
     *   res.send(encryptResponse(screenResponse, aesKeyBuffer, initialVectorBuffer))
     *
     * This is a raw string response, NOT JSON.
     */
    protected function handleEncryptedRequest(Request $request, Company $company): Response
    {
        $rawKey = $company->getConfig('whatsapp_flow_private_key', '');
        $privateKeyPem = $this->normalizePem($rawKey);

        // normalizePem('') returns "\n" which is non-empty — check the raw value
        if (empty(trim($rawKey))) {
            Log::error('WhatsApp Flow webhook: no RSA private key configured', [
                'company_id' => $company->id,
            ]);

            return response('Encryption keys not configured. Click Setup Keys in the flow builder.', 500);
        }

        Log::debug('WhatsApp Flow: private key found, starting decryption');

        try {
            ['payload' => $payload, 'aes_key' => $aesKey, 'iv' => $iv] = $this->decryptPayload($request, $privateKeyPem);

            Log::info('WhatsApp Flow decrypted payload', [
                'action' => $payload['action'] ?? null,
                'screen' => $payload['screen'] ?? null,
                'flow_token' => $payload['flow_token'] ?? null,
                'has_error' => isset($payload['data']['error']),
            ]);

            // Trigger Flowmaker integration only after we know the exchange completes the form.
            $screenResponse = $this->getNextScreen($payload);
            $screenResponse = $this->finalizeDataExchangeResponse($payload, $screenResponse, $company);

            Log::info('WhatsApp Flow response', ['response' => $screenResponse]);

            // IMPORTANT: return raw base64 string — NOT json_encode'd
            // This matches Meta's official sample: res.send(encryptResponse(...))
            $encrypted = $this->encryptResponse($screenResponse, $aesKey, $iv);

            return response($encrypted, 200)
                ->header('Content-Type', 'text/plain');

        } catch (\Throwable $e) {
            Log::error('WhatsApp Flow decryption/encryption error', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'company_id' => $company->id,
            ]);

            // HTTP 421 tells Meta to refresh the business public key and retry.
            // See: https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint
            return response('Decryption failed', 421);
        }
    }

    /**
     * Determine the next screen response based on the decrypted action.
     * Mirrors Meta's getNextScreen() from flow.js.
     */
    protected function getNextScreen(array $decryptedBody): array
    {
        $action = $decryptedBody['action'] ?? null;
        $screen = $decryptedBody['screen'] ?? null;
        $data = $decryptedBody['data'] ?? [];
        $version = $decryptedBody['version'] ?? '3.0';
        $flowToken = $decryptedBody['flow_token'] ?? null;

        // Health check ping — must respond with active status.
        // Include version: Meta's publishing checks expect it (community + sample endpoints).
        if ($action === 'ping') {
            Log::info('WhatsApp Flow health check ping — responding active');

            return [
                'version' => $version ?: '3.0',
                'data' => ['status' => 'active'],
            ];
        }

        // Client-side error notification
        if (isset($data['error'])) {
            Log::warning('WhatsApp Flow client error received', ['data' => $data]);

            return ['data' => ['acknowledged' => true]];
        }

        // INIT — flow opened, return first screen.
        // Resolve the first screen ID from the flow JSON via flow_token so we
        // return the correct screen even when flow_action_payload is omitted.
        $whatsappFlow = $this->resolveWhatsappFlow($flowToken);

        if ($action === 'INIT') {
            // Use the screen from the payload if set; otherwise resolve from the flow JSON.
            // An empty string in the payload means it was not supplied — treat as missing.
            $firstScreenId = (! empty($screen)) ? $screen : $this->resolveFirstScreenId($flowToken);
            Log::info('WhatsApp Flow INIT', ['first_screen' => $firstScreenId, 'flow_token' => $flowToken]);

            return [
                'screen' => $firstScreenId,
                'data' => (object) $this->screenInitService->initDataForScreen($whatsappFlow, $firstScreenId, $flowToken),
            ];
        }

        if ($action === 'BACK') {
            Log::info('WhatsApp Flow BACK', ['screen' => $screen]);

            return [
                'screen' => $screen,
                'data' => (object) [],
            ];
        }

        // data_exchange — form submitted or EmbeddedLink tapped
        if ($action === 'data_exchange') {
            Log::info('WhatsApp Flow data_exchange', [
                'screen' => $screen,
                'data' => $data,
                'flow_token' => $flowToken,
                'data_keys' => array_keys($data),
            ]);

            if (isset($data['url'])) {
                Log::info('EmbeddedLink tapped', ['url' => $data['url']]);
            }

            $templateResponse = $this->dataExchangeResolver->resolve($whatsappFlow, $screen, $data, $flowToken);
            Log::info('WhatsApp Flow data_exchange response', ['response' => $templateResponse]);

            return $templateResponse;
        }

        throw new \Exception('Unhandled action: '.$action);
    }

    /**
     * Resolve the first screen ID from the WhatsApp Flow JSON using the flow_token.
     *
     * flow_token format: "flow_{responseId}_{timestamp}"
     * We look up the WhatsappFlowResponse to find the WhatsappFlow, then read
     * the first screen ID from the stored flow JSON.
     */
    protected function resolveWhatsappFlow(?string $flowToken): ?WhatsappFlow
    {
        if (! $flowToken || ! str_starts_with($flowToken, 'flow_')) {
            return null;
        }

        try {
            $parts = explode('_', $flowToken);
            $responseId = $parts[1] ?? null;

            if (! $responseId) {
                return null;
            }

            $flowResponse = WhatsappFlowResponse::find($responseId);
            if (! $flowResponse) {
                return null;
            }

            return WhatsappFlow::find($flowResponse->whatsapp_flow_id);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp Flow: could not resolve flow from token', [
                'flow_token' => $flowToken,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function resolveFirstScreenId(?string $flowToken): string
    {
        $whatsappFlow = $this->resolveWhatsappFlow($flowToken);

        if (! $whatsappFlow) {
            return 'WELCOME';
        }

        $flowJson = is_array($whatsappFlow->flow_json) ? $whatsappFlow->flow_json : [];
        $firstScreen = $flowJson['screens'][0] ?? null;
        $rawId = $firstScreen['id'] ?? '';

        return (! empty($rawId)) ? (string) $rawId : 'SCREEN_A';
    }

    /**
     * Persist partial answers and complete automations only when Meta receives SUCCESS.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $screenResponse
     * @return array<string, mixed>
     */
    protected function finalizeDataExchangeResponse(array $payload, array $screenResponse, Company $company): array
    {
        if (($payload['action'] ?? null) !== 'data_exchange') {
            return $screenResponse;
        }

        $flowToken = $payload['flow_token'] ?? null;
        $formData = $this->extractSubmittedFormData($payload['data'] ?? []);

        $flowResponse = $this->resolveFlowResponseFromToken($flowToken);
        if (! $flowResponse) {
            return $screenResponse;
        }

        $flowResponse->mergeResponses($formData);

        if (($screenResponse['screen'] ?? null) !== 'SUCCESS') {
            return $screenResponse;
        }

        $flowResponse->refresh();
        $mergedResponses = is_array($flowResponse->responses) ? $flowResponse->responses : $formData;

        $responseParams = array_merge(
            ['flow_token' => $flowToken ?? 'unused'],
            $mergedResponses,
        );

        $screenResponse['data'] = [
            'extension_message_response' => [
                'params' => $responseParams,
            ],
        ];

        $this->triggerFlowmakerIntegration($flowResponse, $company, $mergedResponses);

        return $screenResponse;
    }

    /**
     * @param  array<string, mixed>  $rawData
     * @return array<string, mixed>
     */
    protected function extractSubmittedFormData(array $rawData): array
    {
        return array_filter(
            $rawData,
            fn ($key) => ! in_array($key, ['url', 'error'], true),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function resolveFlowResponseFromToken(?string $flowToken): ?WhatsappFlowResponse
    {
        if (empty($flowToken) || ! str_starts_with($flowToken, 'flow_')) {
            return null;
        }

        $parts = explode('_', $flowToken);
        $responseId = $parts[1] ?? null;

        if (! $responseId) {
            return null;
        }

        return WhatsappFlowResponse::find($responseId);
    }

    /**
     * Handle plain (unencrypted) requests — for local testing only.
     */
    protected function handlePlainRequest(Request $request): Response
    {
        $action = $request->input('action');
        $version = $request->input('version', '3.0');
        $flowToken = $request->input('flow_token');

        Log::info('WhatsApp Flow plain request', ['action' => $action]);

        if ($action === 'ping') {
            return response(json_encode([
                'version' => $version ?: '3.0',
                'data' => ['status' => 'active'],
            ]), 200)->header('Content-Type', 'application/json');
        }

        return response(json_encode([
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => ['flow_token' => $flowToken ?? 'unused'],
                ],
            ],
        ]), 200)->header('Content-Type', 'application/json');
    }

    /**
     * Validate the x-hub-signature-256 header using the app secret.
     * Matches Meta's isRequestSignatureValid() from server.js.
     */
    protected function isRequestSignatureValid(Request $request, Company $company): bool
    {
        $appSecret = $company->getConfig('whatsapp_app_secret', '')
            ?: config('services.whatsapp.app_secret', '');

        if (empty($appSecret)) {
            Log::warning('WhatsApp Flow: APP_SECRET not configured — skipping signature validation');

            return true; // Skip validation if not configured (matches Meta's sample behaviour)
        }

        $signatureHeader = $request->header('x-hub-signature-256', '');
        if (empty($signatureHeader)) {
            Log::warning('WhatsApp Flow: missing x-hub-signature-256 header');

            return false;
        }

        $signature = str_replace('sha256=', '', $signatureHeader);
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Decrypt Meta's encrypted payload.
     * Matches Meta's decryptRequest() from encryption.js exactly.
     *
     * Step 1: RSA-OAEP-SHA256 decrypt the AES key using your private key
     * Step 2: AES-128-GCM decrypt the flow data (last 16 bytes = auth tag)
     *
     * IMPORTANT: Meta uses oaepHash:"sha256" (RSA-OAEP-SHA256).
     * PHP's openssl_private_decrypt() with OPENSSL_PKCS1_OAEP_PADDING defaults
     * to SHA-1, causing an "oaep decoding error" even with the correct key.
     * rsaOaepSha256Decrypt() (EnsuresOpenSsl trait) implements RFC 3447 OAEP-SHA256
     * in pure PHP, matching Meta's encryption exactly.
     */
    protected function decryptPayload(Request $request, string $privateKeyPem): array
    {
        $encryptedAesKey = base64_decode($request->input('encrypted_aes_key'));
        $encryptedFlowData = base64_decode($request->input('encrypted_flow_data'));
        $iv = base64_decode($request->input('initial_vector'));

        // Step 1: RSA-OAEP-SHA256 decrypt the AES key.
        // Meta's client uses oaepHash:"sha256". PHP's OPENSSL_PKCS1_OAEP_PADDING
        // defaults to SHA-1 causing an "oaep decoding error" even with the correct key.
        // rsaOaepSha256Decrypt() implements RFC 3447 OAEP-SHA256 in pure PHP.
        try {
            $aesKey = $this->rsaOaepSha256Decrypt($encryptedAesKey, $privateKeyPem);
        } catch (\Throwable $e) {
            // Return 421 so Meta knows to refresh the public key on the client
            throw new \RuntimeException(
                'Failed to decrypt AES key — verify your private key matches the uploaded public key: '.$e->getMessage()
            );
        }

        // Step 2: AES-128-GCM decrypt — last 16 bytes are the auth tag
        $tag = substr($encryptedFlowData, -16);
        $ciphertext = substr($encryptedFlowData, 0, -16);

        $decrypted = openssl_decrypt($ciphertext, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt flow data: '.openssl_error_string());
        }

        $payload = json_decode($decrypted, true);
        if ($payload === null) {
            throw new \Exception('Decrypted payload is not valid JSON');
        }

        return ['payload' => $payload, 'aes_key' => $aesKey, 'iv' => $iv];
    }

    /**
     * Encrypt the response to send back to Meta.
     * Matches Meta's encryptResponse() from encryption.js exactly.
     *
     * Step 1: Flip every IV byte (bitwise NOT = XOR 0xFF)
     * Step 2: AES-128-GCM encrypt with flipped IV
     * Step 3: Append 16-byte auth tag, base64-encode
     */
    protected function encryptResponse(array $response, string $aesKey, string $iv): string
    {
        // Flip IV bits — PHP ~$string and Meta's sample (~$initialVectorBuffer) are equivalent.
        $flippedIv = ~$iv;

        $tag = '';
        $encrypted = openssl_encrypt(
            json_encode($response, JSON_UNESCAPED_SLASHES),
            'aes-128-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $flippedIv,
            $tag
        );

        if ($encrypted === false) {
            throw new \Exception('Failed to encrypt response: '.openssl_error_string());
        }

        // Ciphertext + auth tag, base64-encoded — matches JS: Buffer.concat([...cipher.getAuthTag()])
        return base64_encode($encrypted.$tag);
    }

    /**
     * When a WhatsApp Flow completes (SUCCESS), resume the Flowmaker automation.
     */
    protected function triggerFlowmakerIntegration(
        WhatsappFlowResponse $flowResponse,
        Company $company,
        array $formData,
    ): void {
        Log::info('WhatsApp Flow: processing completed form submission', [
            'response_id' => $flowResponse->id,
            'company_id' => $company->id,
            'form_data' => $formData,
        ]);

        try {
            $contactId = $flowResponse->contact_id;
            $contact = \Modules\Wpbox\Models\Contact::find($contactId);

            if (! $contact) {
                Log::warning('WhatsApp Flow: contact not found', ['contactId' => $contactId]);

                return;
            }

            $flowResponse->markCompleted($formData);

            Log::info('WhatsApp Flow: response marked completed', [
                'responseId' => $flowResponse->id,
                'formData' => $formData,
            ]);

            $message = \Modules\Wpbox\Models\Message::create([
                'contact_id' => $contactId,
                'company_id' => $company->id,
                'value' => '__whatsapp_flow_completed__',
                'is_message_by_contact' => true,
                'is_campign_messages' => false,
                'status' => 1,
                'fb_message_id' => null,
                'extra' => json_encode($formData),
            ]);

            $companyUser = \App\Models\User::find($company->user_id);

            event(new \Modules\Wpbox\Events\ContactReplies($companyUser, $message, $contact));

            Log::info('WhatsApp Flow: ContactReplies event dispatched', [
                'contactId' => $contactId,
                'responseId' => $flowResponse->id,
                'messageId' => $message->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Flow: Flowmaker integration error', [
                'error' => $e->getMessage(),
                'company_id' => $company->id,
            ]);
        }
    }

    protected function resolveCompanyForFlowWebhook(User $user, Request $request): ?Company
    {
        $companies = Company::where('user_id', $user->id)->orderBy('id')->get();

        if ($companies->isEmpty()) {
            return null;
        }

        if ($companies->count() === 1) {
            return $companies->first();
        }

        if ($request->has('encrypted_flow_data')) {
            foreach ($companies as $company) {
                $rawKey = $company->getConfig('whatsapp_flow_private_key', '');

                if (empty(trim($rawKey))) {
                    continue;
                }

                try {
                    $privateKeyPem = $this->normalizePem($rawKey);
                    $this->decryptPayload($request, $privateKeyPem);

                    return $company;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return $user->currentCompany();
    }
}
