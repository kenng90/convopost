<?php

namespace App\Services;

use App\Models\WhatsappFlow;
use App\Models\WhatsappMetaCredentials;
use App\Traits\EnsuresOpenSsl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappMetaFlowService
{
    use EnsuresOpenSsl;

    protected const META_API_VERSION = 'v18.0';
    protected const META_API_BASE_URL = 'https://graph.facebook.com';

    // -------------------------------------------------------------------------
    // PUBLISH (first-time)
    // -------------------------------------------------------------------------

    /**
     * Publish a flow to Meta WhatsApp Flows API.
     *
     * Strategy:
     *   1. Create the flow asset on Meta (draft — no publish:true yet).
     *   2. If the flow uses data_exchange, verify the health check passes.
     *   3. Call /{flow-id}/publish to make it live.
     *
     * Using a single POST with publish:true is unreliable because Meta runs an
     * async health-check against endpoint_uri and blocks publishing when it
     * cannot reach it in time.  The two-step approach lets us surface the real
     * error before the publish attempt.
     */
    public function publishFlow(WhatsappFlow $flow, $credentials = null): array
    {
        try {
            Log::info('Starting flow publish to Meta', [
                'flow_id'    => $flow->id,
                'flow_name'  => $flow->name,
                'company_id' => $flow->company_id,
            ]);

            $this->validateFlowData($flow);

            if (! $credentials) {
                $credentials = $this->getCredentialsFromCompany($flow->company);
            }

            if (! $credentials) {
                throw new \Exception('Meta API credentials not found. Please configure WhatsApp settings first.');
            }

            $metaFlowJson    = $this->convertToMetaFormat($flow);
            $flowJsonString  = json_encode($metaFlowJson, JSON_UNESCAPED_SLASHES);
            $hasDataExchange = $this->flowHasDataExchange($metaFlowJson);

            $accessToken       = is_array($credentials) ? $credentials['access_token'] : decrypt($credentials->access_token);
            $businessAccountId = is_array($credentials) ? $credentials['business_account_id'] : $credentials->business_account_id;

            // ── Step 1: create or update the flow asset on Meta ──────────────
            if ($flow->meta_flow_id) {
                // Already exists on Meta → push updated JSON, then patch metadata
                $updateResult = $this->pushFlowJsonToMeta($flow->meta_flow_id, $flowJsonString, $accessToken);
                if (! $updateResult['success']) {
                    return $updateResult;
                }

                $metaFlowId = $flow->meta_flow_id;

                // Patch endpoint_uri (set or clear) before publishing
                $this->patchFlowEndpointUri($metaFlowId, $flow, $credentials, $hasDataExchange, $accessToken);
            } else {
                // Brand new flow → create it in draft
                $createResult = $this->createFlowAssetOnMeta(
                    $flow, $metaFlowJson, $flowJsonString, $credentials, $hasDataExchange, $accessToken, $businessAccountId
                );

                if (! $createResult['success']) {
                    return $createResult;
                }

                $metaFlowId = $createResult['meta_flow_id'];
            }

            // ── Step 2: health-check for data_exchange flows ──────────────────
            if ($hasDataExchange) {
                $healthResult = $this->checkFlowEndpointHealth($metaFlowId, $accessToken);
                if (! $healthResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'Endpoint health check failed: ' . $healthResult['message']
                            . ' — make sure your webhook is publicly reachable and returns {"data":{"status":"active"}} for a ping action.',
                    ];
                }

                Log::info('Health check passed', [
                    'flow_id'       => $flow->id,
                    'health_status' => $healthResult['health_status'] ?? 'unknown',
                ]);
            }

            // ── Step 3: publish ───────────────────────────────────────────────
            return $this->sendPublishRequest($flow, $metaFlowId, $accessToken);

        } catch (\Exception $e) {
            return $this->handlePublishException($flow, $e);
        }
    }

    // -------------------------------------------------------------------------
    // RE-PUBLISH (flow already exists on Meta)
    // -------------------------------------------------------------------------

    /**
     * Re-publish an existing Meta flow.
     *
     * After editing a published flow, saving it pushes updated JSON to Meta and
     * resets it to DRAFT. Call this to make it live again.
     *
     * Meta API: POST /{flow-id}/publish
     */
    public function republishFlowOnMeta(WhatsappFlow $flow, $credentials = null): array
    {
        $metaFlowId = $flow->meta_flow_id ?? null;

        if (empty($metaFlowId)) {
            return ['success' => false, 'message' => 'Flow has not been published to Meta yet. Use "Publish to Meta" first.'];
        }

        try {
            if (! $credentials) {
                $credentials = $this->getCredentialsFromCompany($flow->company);
            }

            if (! $credentials) {
                return ['success' => false, 'message' => 'Meta API credentials not found.'];
            }

            $accessToken     = is_array($credentials) ? $credentials['access_token'] : decrypt($credentials->access_token);
            $metaFlowJson    = $this->convertToMetaFormat($flow);
            $hasDataExchange = $this->flowHasDataExchange($metaFlowJson);

            // Patch endpoint_uri on Meta before publishing
            $patchResult = $this->patchFlowEndpointUri($metaFlowId, $flow, $credentials, $hasDataExchange, $accessToken);
            if (! $patchResult['success']) {
                Log::warning('Re-publish: PATCH endpoint_uri failed (continuing anyway)', [
                    'flow_id' => $flow->id,
                    'error'   => $patchResult['message'] ?? 'unknown',
                ]);
            }

            // Health-check for endpoint-powered flows
            if ($hasDataExchange) {
                $healthResult = $this->checkFlowEndpointHealth($metaFlowId, $accessToken);
                if (! $healthResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'Endpoint health check failed: ' . $healthResult['message'],
                    ];
                }
            }

            return $this->sendPublishRequest($flow, $metaFlowId, $accessToken);

        } catch (\Exception $e) {
            Log::error('Exception re-publishing flow on Meta', ['flow_id' => $flow->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE PUBLISH HELPERS
    // -------------------------------------------------------------------------

    /**
     * Create a new flow asset on Meta (draft state, no publish:true).
     */
    private function createFlowAssetOnMeta(
        WhatsappFlow $flow,
        array $metaFlowJson,
        string $flowJsonString,
        $credentials,
        bool $hasDataExchange,
        string $accessToken,
        string $businessAccountId
    ): array {
        $url  = "{$this->getApiBaseUrl()}/{$businessAccountId}/flows";
        $body = [
            'name'       => $flow->name,
            'categories' => [$flow->category ?? 'OTHER'],
            'flow_json'  => $flowJsonString,
            // DO NOT pass publish:true — create in draft, publish separately
        ];

        if ($hasDataExchange) {
            $endpointUri = $this->resolveEndpointUri($flow, $credentials);
            $body['endpoint_uri'] = $endpointUri;

            Log::info('Create: data_exchange flow — including endpoint_uri', [
                'flow_id'      => $flow->id,
                'endpoint_uri' => $endpointUri,
            ]);
        } else {
            // Pure navigate+complete: must NOT include endpoint_uri — Meta would
            // run a health check against a non-existent endpoint and block publishing.
            Log::info('Create: pure navigate flow — no endpoint_uri', ['flow_id' => $flow->id]);
        }

        $response = Http::withToken($accessToken)->timeout(60)->asJson()->post($url, $body);

        if ($response->successful()) {
            $data = $response->json();
            $metaFlowId = $data['id'] ?? null;

            if ($metaFlowId) {
                $flow->update(['meta_flow_id' => $metaFlowId, 'meta_error' => null]);

                Log::info('Flow asset created on Meta (draft)', [
                    'flow_id'      => $flow->id,
                    'meta_flow_id' => $metaFlowId,
                ]);

                return ['success' => true, 'meta_flow_id' => $metaFlowId];
            }
        }

        return $this->buildErrorResponse($flow, $response);
    }

    /**
     * Push updated flow JSON to an existing Meta flow via the /assets endpoint.
     */
    private function pushFlowJsonToMeta(string $metaFlowId, string $flowJsonString, string $accessToken): array
    {
        $url = "{$this->getApiBaseUrl()}/{$metaFlowId}/assets";

        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->attach('file', $flowJsonString, 'flow.json')
            ->post($url, [
                'name'       => 'flow.json',
                'asset_type' => 'FLOW_JSON',
            ]);

        if ($response->successful()) {
            $data = $response->json();

            // Surface Meta validation errors even on HTTP 200
            if (! empty($data['validation_errors'])) {
                $validationMsg = collect($data['validation_errors'])
                    ->map(fn($e) => ($e['error_type'] ?? '') . ': ' . ($e['message'] ?? ''))
                    ->join(' | ');

                Log::warning('Meta returned validation errors during JSON push', [
                    'meta_flow_id' => $metaFlowId,
                    'errors'       => $data['validation_errors'],
                ]);

                return ['success' => false, 'message' => 'Validation errors: ' . $validationMsg];
            }

            Log::info('Flow JSON pushed to Meta successfully', ['meta_flow_id' => $metaFlowId]);
            return ['success' => true];
        }

        $error = $response->json();
        $msg   = $error['error']['message'] ?? 'Unknown error pushing flow JSON';

        Log::error('Failed to push flow JSON to Meta', ['meta_flow_id' => $metaFlowId, 'error' => $error]);
        return ['success' => false, 'message' => $msg];
    }

    /**
     * PATCH the flow metadata on Meta to set or clear the endpoint_uri.
     *
     * - data_exchange flows  → set the correct endpoint URI.
     * - navigate-only flows  → set endpoint_uri to empty string so Meta removes
     *   the health-check requirement (passing null or omitting it has no effect).
     */
    private function patchFlowEndpointUri(
        string $metaFlowId,
        WhatsappFlow $flow,
        $credentials,
        bool $hasDataExchange,
        string $accessToken
    ): array {
        $patchBody = [];

        if ($hasDataExchange) {
            $endpointUri = $this->resolveEndpointUri($flow, $credentials);
            $patchBody['endpoint_uri'] = $endpointUri;

            Log::info('PATCH: setting endpoint_uri (data_exchange flow)', [
                'flow_id'      => $flow->id,
                'meta_flow_id' => $metaFlowId,
                'endpoint_uri' => $endpointUri,
            ]);
        } else {
            // Explicitly blank endpoint_uri to remove health-check requirement
            $patchBody['endpoint_uri'] = '';

            Log::info('PATCH: clearing endpoint_uri (navigate-only flow)', [
                'flow_id'      => $flow->id,
                'meta_flow_id' => $metaFlowId,
            ]);
        }

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch("{$this->getApiBaseUrl()}/{$metaFlowId}", $patchBody);

        if ($response->successful()) {
            return ['success' => true];
        }

        $msg = $response->json()['error']['message'] ?? 'Unknown PATCH error';
        Log::warning('PATCH flow metadata failed', ['meta_flow_id' => $metaFlowId, 'error' => $msg]);
        return ['success' => false, 'message' => $msg];
    }

    /**
     * Query Meta's health_status field and decide if it is safe to publish.
     *
     * Possible values: HEALTHY | WARNING | BLOCKED | null (no endpoint set)
     *
     * We proceed on HEALTHY and WARNING.  BLOCKED means the endpoint failed
     * Meta's health check and publishing would fail.
     */
    private function checkFlowEndpointHealth(string $metaFlowId, string $accessToken): array
    {
        $url      = "{$this->getApiBaseUrl()}/{$metaFlowId}?fields=health_status";
        $response = Http::withToken($accessToken)->timeout(30)->get($url);

        if (! $response->successful()) {
            // Cannot determine status — proceed optimistically
            Log::warning('Could not retrieve health_status from Meta — proceeding anyway', [
                'meta_flow_id' => $metaFlowId,
                'status_code'  => $response->status(),
            ]);
            return ['success' => true, 'health_status' => 'unknown'];
        }

        $healthStatus = $response->json()['health_status'] ?? null;

        Log::info('Flow health_status from Meta', [
            'meta_flow_id'  => $metaFlowId,
            'health_status' => $healthStatus,
        ]);

        if ($healthStatus === 'BLOCKED') {
            return [
                'success'       => false,
                'health_status' => 'BLOCKED',
                'message'       => 'Meta reports your webhook endpoint is BLOCKED. '
                    . 'Ensure it is publicly reachable, returns HTTP 200, '
                    . 'and responds with {"data":{"status":"active"}} for a ping action.',
            ];
        }

        // HEALTHY, WARNING, null (no endpoint set), or any other value → allow publish
        return ['success' => true, 'health_status' => $healthStatus];
    }

    /**
     * Send the POST /{flow-id}/publish request and update the local record.
     */
    private function sendPublishRequest(WhatsappFlow $flow, string $metaFlowId, string $accessToken): array
    {
        $url      = "{$this->getApiBaseUrl()}/{$metaFlowId}/publish";
        $response = Http::withToken($accessToken)->timeout(60)->asJson()->post($url);

        Log::info('Publish request sent', [
            'flow_id'     => $flow->id,
            'meta_flow_id'=> $metaFlowId,
            'status_code' => $response->status(),
        ]);

        if ($response->successful()) {
            $flow->update([
                'meta_flow_id' => $metaFlowId,
                'published_at' => now(),
                'meta_error'   => null,
            ]);

            Log::info('Flow published to Meta successfully', [
                'flow_id'      => $flow->id,
                'meta_flow_id' => $metaFlowId,
            ]);

            return [
                'success'      => true,
                'message'      => 'Flow published to Meta successfully',
                'meta_flow_id' => $metaFlowId,
            ];
        }

        return $this->buildErrorResponse($flow, $response);
    }

    /**
     * Resolve the endpoint URI for data_exchange flows.
     */
    private function resolveEndpointUri(WhatsappFlow $flow, $credentials): string
    {
        return (is_array($credentials) ? ($credentials['endpoint_uri'] ?? null) : null)
            ?? $flow->company->getConfig('whatsapp_flow_endpoint_uri')
            ?? $this->buildFlowWebhookUrl($flow->company);
    }

    /**
     * Build a standardised error response and persist the error on the flow.
     */
    private function buildErrorResponse(WhatsappFlow $flow, $response): array
    {
        $error        = $response->json();
        $statusCode   = $response->status();
        $errorMessage = $error['error']['error_user_msg']
            ?? $error['error']['message']
            ?? 'Unknown error occurred';

        Log::error('Meta API error response', [
            'flow_id'       => $flow->id,
            'status_code'   => $statusCode,
            'error_message' => $errorMessage,
            'full_error'    => $error,
        ]);

        $flow->update([
            'meta_error' => [
                'status_code' => $statusCode,
                'code'        => $error['error']['code'] ?? null,
                'message'     => $errorMessage,
                'response'    => $error,
                'occurred_at' => now()->toIso8601String(),
            ],
        ]);

        return ['success' => false, 'message' => $errorMessage, 'error' => $error];
    }

    /**
     * Handle unexpected exceptions during publishing.
     */
    private function handlePublishException(WhatsappFlow $flow, \Exception $e): array
    {
        $errorData = [
            'message'         => $e->getMessage(),
            'occurred_at'     => now()->toIso8601String(),
            'exception_class' => get_class($e),
        ];

        try {
            $flow->update(['meta_error' => $errorData]);
        } catch (\Exception $ignored) {
        }

        Log::error('Exception while publishing flow to Meta', [
            'flow_id'   => $flow->id,
            'message'   => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'An error occurred while publishing: ' . $e->getMessage(),
            'error'   => $errorData,
        ];
    }

    // -------------------------------------------------------------------------
    // DATA_EXCHANGE DETECTION
    // -------------------------------------------------------------------------

    /**
     * Return true if the flow requires a backend endpoint.
     *
     * Triggers:
     *   1. Any button/footer/link has on-click-action.name === "data_exchange"
     *   2. Any dropdown/radio has on-select-action.name === "data_exchange"
     *   3. Any screen has refresh_on_back === true
     *   4. Presence of routing_model (only added for endpoint-powered flows)
     *
     * We do NOT treat routing_model alone as a trigger because we add it to
     * all flows for navigation purposes. The definitive check is the action name.
     */
    protected function flowHasDataExchange(array $metaFlowJson): bool
    {
        foreach ($metaFlowJson['screens'] ?? [] as $screen) {
            // Trigger 3: refresh_on_back causes an endpoint call on back-navigation
            if (! empty($screen['refresh_on_back'])) {
                return true;
            }

            if ($this->componentsHaveDataExchange($screen['layout']['children'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively walk components and check for data_exchange actions.
     */
    private function componentsHaveDataExchange(array $components): bool
    {
        foreach ($components as $component) {
            // on-click-action (Footer, EmbeddedLink, buttons)
            $clickAction = $component['on-click-action'] ?? $component['on_click_action'] ?? null;
            if (isset($clickAction['name']) && $clickAction['name'] === 'data_exchange') {
                return true;
            }

            // on-select-action (Dropdown, RadioButtonsGroup, CheckboxGroup)
            $selectAction = $component['on-select-action'] ?? $component['on_select_action'] ?? null;
            if (isset($selectAction['name']) && $selectAction['name'] === 'data_exchange') {
                return true;
            }

            // Recurse into Form or other container children
            if (! empty($component['children'])) {
                if ($this->componentsHaveDataExchange($component['children'])) {
                    return true;
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // FLOW FORMAT CONVERSION
    // -------------------------------------------------------------------------

    private function getMetaDataType(string $fieldType): string
{
    return match ($fieldType) {
        'date'                       => 'string', // dates come as "YYYY-MM-DD" strings
        'checkbox'                   => 'array',
        'radio', 'chips', 'select'   => 'string',
        default                      => 'string',
    };
}

private function getMetaDataExample(string $fieldType): string
{
    return match ($fieldType) {
        'date'     => '2026-01-01',
        'checkbox' => 'option1',
        default    => 'example',
    };
}
    /**
     * Convert local flow format to Meta Flow JSON format.
     */
  
    // -------------------------------------------------------------------------
    // COMPONENT CONVERSION
    // -------------------------------------------------------------------------

    /**
     * Convert local fields to Meta Flow components.
     */

    /**
 * Convert local fields to Meta Flow components.
 *
 * Meta requires ALL input fields to be wrapped in a single Form component.
 * The Form's Footer/submit button must be INSIDE the Form's children too.
 * Without Form wrapping, nfm_reply only returns {flow_token} — no field data.
 */

/**
 * Get the component name for a field (matches what convertFieldToComponent produces)
 */



    // -------------------------------------------------------------------------
    // IMAGE HANDLING
    // -------------------------------------------------------------------------
// -------------------------------------------------------------------------
// FLOW FORMAT CONVERSION
// -------------------------------------------------------------------------

public function convertToMetaFormat(WhatsappFlow $flow): array
{
    $screens     = $flow->flow_json['screens'] ?? [];
    $screenCount = count($screens);

    $allPreviousFields = [];
    $convertedScreens  = [];

    foreach ($screens as $index => $screen) {
        $isTerminal   = ($index === $screenCount - 1) || ($screen['terminal'] ?? false);
        $nextScreenId = null;

        if (! $isTerminal && isset($screens[$index + 1])) {
            $nextScreenId = (string) ($screens[$index + 1]['id'] ?? 'SCREEN_' . chr(65 + $index + 1));
        }

        // ── Build screen data model ───────────────────────────────────────────
        // Start with any explicit data declarations from the source screen
        $screenData = (! empty($screen['data']) && is_array($screen['data']))
            ? $screen['data']
            : [];

        // Declare every field from previous screens in this screen's data model.
        // Meta requires this so it accepts those fields in the navigate payload.
        foreach ($allPreviousFields as $prevField) {
            $componentName = $this->getComponentName($prevField);
            if (! $componentName) {
                continue;
            }

            $fieldType = $prevField['type'] ?? 'text';

            $screenData[$componentName] = match ($fieldType) {
                'date'                     => ['type' => 'string',  '__example__' => '2026-01-01'],
                'checkbox'                 => ['type' => 'array',   'items' => ['type' => 'string'], '__example__' => ['option1']],
                'radio', 'chips', 'select' => ['type' => 'string',  '__example__' => 'option1'],
                'optin'                    => ['type' => 'boolean', '__example__' => true],  // ← add this
                default                    => ['type' => 'string',  '__example__' => 'example text'],
            };
        }

        Log::debug('Building screen', [
            'screen_id'           => $screen['id'] ?? 'unknown',
            'is_terminal'         => $isTerminal,
            'next_screen'         => $nextScreenId,
            'previous_field_count'=> count($allPreviousFields),
            'screen_data_keys'    => array_keys($screenData),
        ]);

        // ── Build the converted screen (single assignment) ────────────────────
        $convertedScreen = [
            'id'       => (string) ($screen['id'] ?? 'SCREEN_' . chr(65 + $index)),
            'title'    => $screen['title'] ?? 'Screen',
            'terminal' => $isTerminal,
            // Empty array must encode as {} not [] — use stdClass for empty
            'data'     => empty($screenData) ? new \stdClass() : $screenData,
            'layout'   => [
                'type'     => 'SingleColumnLayout',
                'children' => $this->convertFieldsToComponents(
                    $screen['fields'] ?? [],
                    $nextScreenId,
                    $isTerminal,
                    $allPreviousFields
                ),
            ],
        ];

        if ($isTerminal) {
            $convertedScreen['success'] = true;
        }

        if (! empty($screen['refresh_on_back'])) {
            $convertedScreen['refresh_on_back'] = true;
        }

        $convertedScreens[] = $convertedScreen;

        // ── Accumulate AFTER building — so next screen can reference these ────
        $inputOnlyTypes    = ['text', 'textarea', 'radio', 'checkbox', 'select', 'date', 'chips', 'optin'];
        $allPreviousFields = array_merge(
            $allPreviousFields,
            array_values(array_filter(
                $screen['fields'] ?? [],
                fn($f) => in_array($f['type'] ?? '', $inputOnlyTypes, true)
            ))
        );
    }

    // Build routing model (always needed for navigation)
    $routingModel = [];
    foreach ($convertedScreens as $index => $screen) {
        $nextIndex                   = $index + 1;
        $routingModel[$screen['id']] = $nextIndex < count($convertedScreens)
            ? [$convertedScreens[$nextIndex]['id']]
            : [];
    }

    $metaFlow = [
        'version'       => '7.3',
        'routing_model' => $routingModel,
        'screens'       => $convertedScreens,
    ];

    // data_api_version is ONLY required for endpoint-powered flows
    if ($this->flowHasDataExchange(['screens' => $convertedScreens])) {
        $metaFlow['data_api_version'] = '3.0';
    }

    Log::debug('Flow conversion completed', [
        'flow_id'           => $flow->id,
        'has_data_exchange' => isset($metaFlow['data_api_version']),
        'screen_count'      => count($metaFlow['screens']),
    ]);

    return $metaFlow;
}

// -------------------------------------------------------------------------
// COMPONENT CONVERSION
// -------------------------------------------------------------------------

/**
 * Convert local fields to Meta Flow components.
 *
 * Rules:
 * - Display-only components (heading, image, etc.) go OUTSIDE the Form
 * - All input fields + the footer go INSIDE a single Form component
 * - The footer navigate/complete payload must reference all form fields
 *   using ${form.x} for current screen and ${data.x} for previous screens
 */
protected function convertFieldsToComponents(
    array $fields,
    ?string $nextScreenId = null,
    bool $isTerminal = false,
    array $previousScreenFields = []
): array {
    $displayOnlyTypes = [
        'heading', 'subheading', 'body', 'caption',
        'richtext', 'image', 'image_carousel',
    ];
    $footerTypes = ['navigate', 'button', 'footer'];

    $outsideChildren = [];
    $inputFields     = [];
    $footerFields    = [];

    foreach ($fields as $field) {
        $type = $field['type'] ?? 'text';
        if (in_array($type, $displayOnlyTypes, true)) {
            $outsideChildren[] = $this->convertFieldToComponent($field, $nextScreenId, $isTerminal);
        } elseif (in_array($type, $footerTypes, true)) {
            $footerFields[] = $field;
        } else {
            $inputFields[] = $field;
        }
    }

    // No form content at all — just return display components
    if (empty($inputFields) && empty($footerFields)) {
        return $outsideChildren;
    }

    // ── Build the action payload ──────────────────────────────────────────────
    // This must be built BEFORE the footer closure so it is fully populated.
    //
    // Current screen fields  → ${form.x}   (they live in the Form on this screen)
    // Previous screen fields → ${data.x}   (they arrived via the navigate payload)
    $actionPayload = [];

    foreach ($inputFields as $field) {
        $componentName = $this->getComponentName($field);
        if ($componentName) {
            $actionPayload[$componentName] = '${form.' . $componentName . '}';
        }
    }

    foreach ($previousScreenFields as $field) {
        $componentName = $this->getComponentName($field);
        if ($componentName) {
            $actionPayload[$componentName] = '${data.' . $componentName . '}';
        }
    }

    Log::debug('convertFieldsToComponents: payload built', [
        'is_terminal'         => $isTerminal,
        'input_field_count'   => count($inputFields),
        'previous_field_count'=> count($previousScreenFields),
        'action_payload_keys' => array_keys($actionPayload),
    ]);

    // ── Convert input fields ──────────────────────────────────────────────────
    $convertedInputs = array_map(
        fn($f) => $this->convertFieldToComponent($f, $nextScreenId, $isTerminal),
        $inputFields
    );

    // ── Convert footer fields, injecting the payload ──────────────────────────
    // $actionPayload is captured by value — fully built before this runs
    $convertedFooters = array_map(
        function ($field) use ($nextScreenId, $isTerminal, $actionPayload) {
            $component  = $this->convertFieldToComponent($field, $nextScreenId, $isTerminal);
            $actionName = $component['on-click-action']['name'] ?? '';

            $payloadToInject = empty($actionPayload) ? new \stdClass() : $actionPayload;

            if ($isTerminal && $actionName === 'complete') {
                $component['on-click-action']['payload'] = $payloadToInject;
            } elseif (! $isTerminal && $actionName === 'navigate') {
                $component['on-click-action']['payload'] = $payloadToInject;
            }

            return $component;
        },
        $footerFields
    );

    $formComponent = [
        'type'     => 'Form',
        'name'     => 'main_form',
        'children' => array_merge($convertedInputs, $convertedFooters),
    ];

    return array_merge($outsideChildren, [$formComponent]);
}

/**
 * Get the Meta component name for a field.
 * Must match the name produced by convertFieldToComponent exactly.
 */
private function getComponentName(array $field): ?string
{
    $type    = $field['type'] ?? '';
    $fieldId = $field['id'] ?? '';

    if (empty($fieldId)) {
        return null;
    }

    return match ($type) {
        'text'     => 'text_' . $fieldId,
        'textarea' => 'textarea_' . $fieldId,
        'radio'    => 'radio_' . $fieldId,
        'checkbox' => 'checkbox_' . $fieldId,
        'select'   => 'select_' . $fieldId,
        'date'     => 'date_' . $fieldId,
        'chips'    => 'chips_' . $fieldId,
        'optin'    => 'optin_' . $fieldId,
        default    => null,
    };
}

/**
 * Convert a single field to a Meta Flow component.
 * Follows Meta's exact JSON spec for Flow JSON v7.3.
 */
protected function convertFieldToComponent(
    array $field,
    ?string $nextScreenId = null,
    bool $isTerminal = false
): array {
    $type    = $field['type'] ?? 'text';
    $fieldId = $field['id'] ?? 'field';

    return match ($type) {

        // ── Text display ──────────────────────────────────────────────────────
        'heading' => [
            'type' => 'TextHeading',
            'text' => $field['label'] ?? '',
        ],
        'subheading' => [
            'type' => 'TextSubheading',
            'text' => $field['label'] ?? '',
        ],
        'body' => [
            'type'     => 'TextBody',
            'text'     => $field['label'] ?? '',
            'markdown' => $field['markdown'] ?? false,
        ],
        'caption' => [
            'type' => 'TextCaption',
            'text' => $field['label'] ?? '',
        ],
        'richtext' => [
            'type' => 'RichText',
            'text' => $field['label'] ?? '',
        ],

        // ── Input components ──────────────────────────────────────────────────
        'text' => [
            'type'       => 'TextInput',
            'name'       => 'text_' . $fieldId,
            'label'      => $field['label'] ?? '',
            'required'   => $field['required'] ?? false,
            'input-type' => 'text',
        ],
        'textarea' => [
            'type'       => 'TextArea',
            'name'       => 'textarea_' . $fieldId,
            'label'      => $field['label'] ?? '',
            'required'   => $field['required'] ?? false,
            'max-length' => 4096,
        ],
        'radio' => [
            'type'        => 'RadioButtonsGroup',
            'name'        => 'radio_' . $fieldId,
            'label'       => $field['label'] ?? '',
            'required'    => $field['required'] ?? false,
            'data-source' => $this->convertOptionsToDataSource($field['options'] ?? []),
        ],
        'checkbox' => [
            'type'        => 'CheckboxGroup',
            'name'        => 'checkbox_' . $fieldId,
            'label'       => $field['label'] ?? '',
            'required'    => $field['required'] ?? false,
            'data-source' => $this->convertOptionsToDataSource($field['options'] ?? []),
        ],
        'select' => [
            'type'        => 'Dropdown',
            'name'        => 'select_' . $fieldId,
            'label'       => $field['label'] ?? '',
            'required'    => $field['required'] ?? false,
            'data-source' => $this->convertOptionsToDataSource($field['options'] ?? []),
        ],
        'date' => [
            'type'     => 'DatePicker',
            'name'     => 'date_' . $fieldId,
            'label'    => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
        ],
        'chips' => [
            'type'        => 'RadioButtonsGroup',
            'name'        => 'chips_' . $fieldId,
            'label'       => $field['label'] ?? '',
            'data-source' => $this->convertOptionsToDataSource($field['options'] ?? []),
        ],

        // ── Media ─────────────────────────────────────────────────────────────
        'image' => [
            'type'       => 'Image',
            'src'        => $this->resolveImageSrc($field['image_url'] ?? ''),
            'height'     => $field['height'] ?? 300,
            'scale-type' => $field['scale_type'] ?? 'contain',
        ],
        'media_upload' => [
            'type'       => 'TextInput',
            'name'       => 'media_' . $fieldId,
            'label'      => $field['label'] ?? 'Upload File',
            'required'   => $field['required'] ?? false,
            'input-type' => 'text',
        ],
        'image_carousel' => [
            'type'   => 'ImageCarousel',
            'images' => ! empty($field['images'])
                ? array_map(fn($img) => [
                    'src'      => $this->resolveImageSrc($img['src'] ?? $img['image_url'] ?? ''),
                    'alt-text' => $img['alt_text'] ?? $img['label'] ?? 'Image',
                ], $field['images'])
                : [['src' => '', 'alt-text' => 'Image']],
        ],

        // ── Rich content ──────────────────────────────────────────────────────
        'embedded_link' => [
            'type' => 'EmbeddedLink',
            'text' => $field['button_label'] ?? $field['label'] ?? 'Open Link',
            'on-click-action' => [
                'name'    => 'data_exchange',
                'payload' => ['url' => $field['url'] ?? ''],
            ],
        ],
        'optin' => [
            'type'     => 'OptIn',
            'name'     => 'optin_' . $fieldId,
            'label'    => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
        ],

        // ── Navigation / submit ───────────────────────────────────────────────
        // NOTE: payload is intentionally empty here — convertFieldsToComponents
        // injects the correct field references after this method returns.
        'navigate' => [
            'type'  => 'Footer',
            'label' => $field['label'] ?? 'Continue',
            'on-click-action' => $isTerminal
                ? ['name' => 'complete', 'payload' => new \stdClass()]
                : [
                    'name'    => 'navigate',
                    'next'    => ['type' => 'screen', 'name' => $nextScreenId ?? 'NEXT_SCREEN'],
                    'payload' => new \stdClass(),
                  ],
        ],
        'footer' => [
            'type'  => 'Footer',
            'label' => $field['label'] ?? 'Continue',
            'on-click-action' => $isTerminal
                ? ['name' => 'complete', 'payload' => new \stdClass()]
                : [
                    'name'    => 'navigate',
                    'next'    => ['type' => 'screen', 'name' => $nextScreenId ?? 'NEXT_SCREEN'],
                    'payload' => new \stdClass(),
                  ],
        ],
        'button' => [
            'type'  => 'Footer',
            'label' => $field['label'] ?? 'Submit',
            'on-click-action' => $isTerminal
                ? ['name' => 'complete', 'payload' => new \stdClass()]
                : [
                    'name'    => 'navigate',
                    'next'    => ['type' => 'screen', 'name' => $nextScreenId ?? 'NEXT_SCREEN'],
                    'payload' => new \stdClass(),
                  ],
        ],

        // ── Logic ─────────────────────────────────────────────────────────────
        'if_condition' => [
            'type'        => 'If',
            'condition'   => $field['condition'] ?? '${true}',
            'then-action' => [
                'name'        => $field['then_action'] ?? 'navigate',
                'next-screen' => 'NEXT_SCREEN',
            ],
            'else-action' => [
                'name'        => $field['else_action'] ?? 'navigate',
                'next-screen' => 'CURRENT_SCREEN',
            ],
        ],
        'switch' => [
            'type'  => 'Switch',
            'cases' => array_map(fn($case) => [
                'condition' => $case['condition'] ?? '',
                'action'    => [
                    'name'        => 'navigate',
                    'next-screen' => $case['next_screen'] ?? 'NEXT_SCREEN',
                ],
            ], $field['cases'] ?? []),
        ],

        default => [
            'type' => 'TextBody',
            'text' => $field['label'] ?? 'Unknown Field Type',
        ],
    };
}
    /**
     * Resolve an image source to a base64 string as required by Meta's Flow JSON.
     *
     * Meta Image/ImageCarousel components reject plain URLs — src must be raw
     * base64 (NOT a data URI prefix).  If the value is already a data URI, the
     * prefix is stripped and the raw base64 is returned.
     */
    protected function resolveImageSrc(string $src): string
    {
        $src = trim($src);

        if (empty($src)) {
            return '';
        }

        // Already a data URI → strip prefix, return raw base64
        if (str_starts_with($src, 'data:image')) {
            $parts = explode(';base64,', $src, 2);
            if (count($parts) === 2) {
                return $parts[1];
            }
            Log::warning('data URI missing ;base64, marker', ['src' => substr($src, 0, 100)]);
            return '';
        }

        try {
            Log::info('Fetching image for base64 conversion', ['url' => $src]);

            $response = Http::timeout(15)->get($src);

            if (! $response->successful()) {
                Log::warning('Image fetch failed', ['url' => $src, 'status' => $response->status()]);
                return $src;
            }

            $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? 'image/jpeg')[0]));
            if ($contentType === 'image/jpg') {
                $contentType = 'image/jpeg';
            }

            if (! str_starts_with($contentType, 'image/')) {
                Log::warning('URL did not return an image', ['url' => $src, 'content_type' => $contentType]);
                return $src;
            }

            $imageBody = $response->body();

            // Decompress gzip if the server did not do so
            if (str_starts_with($imageBody, "\x1f\x8b")) {
                $imageBody = gzdecode($imageBody);
                if ($imageBody === false) {
                    Log::warning('Failed to gzip-decode image', ['url' => $src]);
                    return $src;
                }
            }

            $base64 = base64_encode($imageBody);

            $estimatedBytes = strlen($base64) * 3 / 4;
            if ($estimatedBytes > 300 * 1024) {
                Log::warning('Image may exceed Meta Flows 300 KB limit', [
                    'url'             => $src,
                    'estimated_bytes' => $estimatedBytes,
                ]);
            }

            Log::info('Image converted to base64', [
                'url'           => $src,
                'content_type'  => $contentType,
                'base64_length' => strlen($base64),
            ]);

            return $base64;

        } catch (\Exception $e) {
            Log::warning('Exception while converting image to base64', [
                'url'   => $src,
                'error' => $e->getMessage(),
            ]);
            return $src;
        }
    }

    // -------------------------------------------------------------------------
    // KEY MANAGEMENT
    // -------------------------------------------------------------------------

    /**
     * Generate a 2048-bit RSA key pair for Flow endpoint encryption.
     * Stores the private key in company config and returns the public key.
     */
    public function generateFlowEncryptionKeys(\App\Models\Company $company): array
    {
        Log::info('Generating RSA key pair for WhatsApp Flow encryption', ['company_id' => $company->id]);

        $opensslConfPath = $this->findOpenSslConf();

        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        if ($opensslConfPath) {
            $config['config'] = $opensslConfPath;
        }

        $keyPair = openssl_pkey_new($config);
        if (! $keyPair) {
            throw new \Exception(
                'Failed to generate RSA key pair: ' . openssl_error_string()
                . '. On Windows, set OPENSSL_CONF in your .env pointing to openssl.cnf.'
            );
        }

        openssl_pkey_export($keyPair, $privateKeyPem, null, $opensslConfPath ? ['config' => $opensslConfPath] : []);

        $keyDetails   = openssl_pkey_get_details($keyPair);
        $publicKeyPem = $keyDetails['key'];

        $privateKeyPem = $this->normalizePem($privateKeyPem);
        $publicKeyPem  = $this->normalizePem($publicKeyPem);

        if (str_contains($privateKeyPem, 'BEGIN RSA PRIVATE KEY')) {
            Log::info('PKCS#1 key detected — converting to PKCS#8', ['company_id' => $company->id]);
            $privateKeyPem = $this->convertPkcs1ToPkcs8($privateKeyPem);
        }

        $company->setConfig('whatsapp_flow_private_key', $privateKeyPem);

        return ['public_key' => $publicKeyPem, 'private_key_stored' => true];
    }

    /**
     * Upload the RSA public key to Meta.
     *
     * Meta API: POST /{phone-number-id}/whatsapp_business_encryption
     */
    public function uploadPublicKeyToMeta(\App\Models\Company $company, string $publicKeyPem, $credentials = null): array
    {
        if (! $credentials) {
            $credentials = $this->getCredentialsFromCompany($company);
        }

        if (! $credentials) {
            throw new \Exception('Meta API credentials not found.');
        }

        $accessToken   = is_array($credentials) ? $credentials['access_token'] : decrypt($credentials->access_token);
        $phoneNumberId = is_array($credentials) ? ($credentials['phone_number_id'] ?? null) : ($credentials->phone_number_id ?? null);

        if (empty($phoneNumberId)) {
            throw new \Exception('Phone Number ID not found in credentials.');
        }

        $url = "{$this->getApiBaseUrl()}/{$phoneNumberId}/whatsapp_business_encryption";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->post($url, ['business_public_key' => $publicKeyPem]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Public key uploaded to Meta successfully.'];
        }

        $error = $response->json()['error']['message'] ?? 'Unknown error';
        return ['success' => false, 'message' => 'Failed to upload public key: ' . $error, 'error' => $response->json()];
    }

    // -------------------------------------------------------------------------
    // SYNC / STATUS
    // -------------------------------------------------------------------------

    /**
     * Fetch the current status of a single flow from Meta and update the local record.
     */
    public function syncStatusFromMeta(WhatsappFlow $flow, $credentials = null): array
    {
        $metaFlowId = $flow->meta_flow_id ?? null;

        if (empty($metaFlowId)) {
            return ['success' => false, 'message' => 'Flow has no Meta Flow ID — publish it first.'];
        }

        try {
            if (! $credentials) {
                $credentials = $this->getCredentialsFromCompany($flow->company);
            }

            if (! $credentials) {
                return ['success' => false, 'message' => 'Meta API credentials not found.'];
            }

            $accessToken = is_array($credentials) ? $credentials['access_token'] : decrypt($credentials->access_token);

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get("{$this->getApiBaseUrl()}/{$metaFlowId}", [
                    'fields' => 'id,name,status,categories,validation_errors',
                ]);

            if (! $response->successful()) {
                $error = $response->json()['error']['message'] ?? 'Unknown error';
                return ['success' => false, 'message' => "Meta API error: {$error}"];
            }

            $data        = $response->json();
            $metaStatus  = strtolower($data['status'] ?? 'draft');
            $localStatus = match ($metaStatus) {
                'published'  => 'published',
                'deprecated' => 'archived',
                default      => 'draft',
            };

            $updates = ['status' => $localStatus];

            if ($localStatus === 'published' && empty($flow->published_at)) {
                $updates['published_at'] = now();
            }

            if (in_array($metaStatus, ['published', 'draft']) && empty($data['validation_errors'])) {
                $updates['meta_error'] = null;
            }

            $flow->update($updates);

            Log::info('syncStatusFromMeta: status synced', [
                'flow_id'      => $flow->id,
                'meta_status'  => $metaStatus,
                'local_status' => $localStatus,
            ]);

            return [
                'success'      => true,
                'meta_status'  => strtoupper($metaStatus),
                'local_status' => $localStatus,
                'message'      => 'Status synced: Meta reports this flow is ' . strtoupper($metaStatus) . '.',
            ];

        } catch (\Exception $e) {
            Log::error('syncStatusFromMeta exception', ['flow_id' => $flow->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync statuses for all flows that have a meta_flow_id for a given company.
     */
    public function syncAllStatuses(\App\Models\Company $company): array
    {
        $credentials = $this->getCredentialsFromCompany($company);

        if (! $credentials) {
            return ['success' => false, 'message' => 'Meta API credentials not found.'];
        }

        $flows = WhatsappFlow::where('company_id', $company->id)
            ->whereNotNull('meta_flow_id')
            ->get();

        if ($flows->isEmpty()) {
            return ['success' => true, 'message' => 'No published flows to sync.', 'synced' => 0];
        }

        $synced = 0;
        $failed = 0;

        foreach ($flows as $flow) {
            $this->syncStatusFromMeta($flow, $credentials)['success'] ? $synced++ : $failed++;
        }

        $message = "Synced {$synced} flow(s) from Meta.";
        if ($failed > 0) {
            $message .= " {$failed} could not be synced (check logs).";
        }

        return ['success' => true, 'message' => $message, 'synced' => $synced, 'failed' => $failed];
    }

    /**
     * Update flow JSON on Meta for an already-existing flow (sets it back to DRAFT).
     */
    public function updateFlowOnMeta(WhatsappFlow $flow, $credentials = null): array
    {
        $metaFlowId = $flow->meta_flow_id ?? null;

        if (empty($metaFlowId)) {
            return ['success' => false, 'message' => 'Flow has not been published to Meta yet.'];
        }

        try {
            if (! $credentials) {
                $credentials = $this->getCredentialsFromCompany($flow->company);
            }

            if (! $credentials) {
                return ['success' => false, 'message' => 'Meta API credentials not found.'];
            }

            $accessToken    = is_array($credentials) ? $credentials['access_token'] : decrypt($credentials->access_token);
            $metaFlowJson   = $this->convertToMetaFormat($flow);
            $flowJsonString = json_encode($metaFlowJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            Log::debug('FULL FLOW JSON BEING SENT TO META', [
                'json' => json_encode($metaFlowJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
            $result = $this->pushFlowJsonToMeta($metaFlowId, $flowJsonString, $accessToken);

            if (! $result['success']) {
                return $result;
            }

            return ['success' => true, 'message' => 'Flow JSON updated on Meta. Re-publish to apply changes.'];

        } catch (\Exception $e) {
            Log::error('Exception updating flow on Meta', ['flow_id' => $flow->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync a flow from Meta (retrieve its current JSON).
     */
    public function syncFlowFromMeta(string $metaFlowId, WhatsappMetaCredentials $credentials): array
    {
        try {
            $response = Http::withToken(decrypt($credentials->access_token))
                ->timeout(60)
                ->get("{$this->getApiBaseUrl()}/{$metaFlowId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'error' => $response->json()];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // DELETE / DEPRECATE
    // -------------------------------------------------------------------------

    /**
     * Delete a flow from Meta.
     * DRAFT flows can be deleted directly; PUBLISHED flows must be deprecated first.
     */
    public function deleteFlowOnMeta(WhatsappFlow $flow, $credentials = null): array
    {
        $metaFlowId = $flow->meta_flow_id ?? null;

        if (empty($metaFlowId)) {
            return ['success' => true, 'message' => 'Flow was not published to Meta — nothing to delete.'];
        }

        try {
            if (! $credentials) {
                $credentials = $this->getCredentialsFromCompany($flow->company);
            }

            if (! $credentials) {
                return ['success' => false, 'message' => 'Meta API credentials not found.'];
            }

            $accessToken = is_array($credentials) ? $credentials['access_token'] : decrypt($credentials->access_token);
            $deleteUrl   = "{$this->getApiBaseUrl()}/{$metaFlowId}";
            $response    = Http::withToken($accessToken)->timeout(30)->delete($deleteUrl);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Flow deleted from Meta.'];
            }

            $error        = $response->json();
            $errorCode    = $error['error']['code'] ?? null;
            $errorSubcode = $error['error']['error_subcode'] ?? null;
            $errorMessage = $error['error']['message'] ?? '';

            $needsDeprecation = $errorCode === 139004
                || $errorSubcode === 4016026
                || str_contains(strtolower($errorMessage), 'deprecate');

            if ($needsDeprecation) {
                $deprecateResp = Http::withToken($accessToken)
                    ->timeout(30)
                    ->post("{$this->getApiBaseUrl()}/{$metaFlowId}/deprecate");

                if (! $deprecateResp->successful()) {
                    $depError = $deprecateResp->json()['error']['message'] ?? 'Unknown error';
                    return ['success' => false, 'message' => "Could not deprecate on Meta: {$depError}"];
                }

                $retryResp = Http::withToken($accessToken)->timeout(30)->delete($deleteUrl);

                if ($retryResp->successful()) {
                    return ['success' => true, 'message' => 'Flow deprecated and deleted from Meta.'];
                }

                $retryError = $retryResp->json()['error']['message'] ?? 'Unknown error';
                return ['success' => false, 'message' => "Deprecated but could not delete: {$retryError}"];
            }

            return ['success' => false, 'message' => "Meta API error: {$errorMessage}"];

        } catch (\Exception $e) {
            Log::error('Exception deleting flow from Meta', ['meta_flow_id' => $metaFlowId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // UTILITIES
    // -------------------------------------------------------------------------

    protected function getApiBaseUrl(): string
    {
        return self::META_API_BASE_URL . '/' . self::META_API_VERSION;
    }

    protected function buildFlowWebhookUrl(\App\Models\Company $company): string
    {
        $token = $company->getConfig('plain_token', '');

        if (empty($token)) {
            Log::warning('buildFlowWebhookUrl: no plain_token found', ['company_id' => $company->id]);
            return config('app.url') . '/webhook/wpbox/flows/unknown';
        }

        return config('app.url') . '/webhook/wpbox/flows/' . $token;
    }

    protected function convertOptionsToDataSource(array $options): array
    {
        return array_map(fn($option) => [
            'id'    => (string) ($option['value'] ?? $option['id'] ?? ''),
            'title' => $option['label'] ?? '',
        ], $options);
    }

    protected function getCredentialsFromCompany($company): ?array
    {
        if (! $company) {
            return null;
        }

        $accessToken       = $company->getConfig('whatsapp_permanent_access_token');
        $businessAccountId = $company->getConfig('whatsapp_business_account_id');
        $phoneNumberId     = $company->getConfig('whatsapp_phone_number_id');

        if (! $accessToken || ! $businessAccountId) {
            return null;
        }

        return [
            'access_token'       => $accessToken,
            'business_account_id'=> $businessAccountId,
            'waba_id'            => $businessAccountId,
            'phone_number_id'    => $phoneNumberId,
        ];
    }

    // -------------------------------------------------------------------------
    // VALIDATION
    // -------------------------------------------------------------------------

    protected function validateFlowData(WhatsappFlow $flow): void
    {
        if (empty($flow->name)) {
            throw new \Exception('Flow name is required');
        }

        if (empty($flow->flow_json['screens'])) {
            throw new \Exception('Flow must have at least one screen');
        }

        foreach ($flow->flow_json['screens'] as $index => $screen) {
            if (empty($screen['title'])) {
                throw new \Exception("Screen at index {$index} must have a title");
            }

            if (! empty($screen['id']) && ! preg_match('/^[A-Za-z_]+$/', $screen['id'])) {
                throw new \Exception("Screen ID must contain only letters and underscores. Got: {$screen['id']}");
            }

            $this->validateRichTextRules($screen, $index, $flow->id);
        }

        // Validate required media / URL fields
        foreach ($flow->flow_json['screens'] as $screenIndex => $screen) {
            $screenTitle = $screen['title'] ?? ('Screen ' . ($screenIndex + 1));

            foreach ($screen['fields'] ?? [] as $field) {
                $fieldType = $field['type'] ?? '';

                if ($fieldType === 'image' && empty(trim($field['image_url'] ?? ''))) {
                    throw new \Exception(
                        "\"{$screenTitle}\": Image component has no source. Enter a public image URL or base64 data URI."
                    );
                }

                if ($fieldType === 'image_carousel') {
                    $images = $field['images'] ?? [];
                    if (empty($images)) {
                        throw new \Exception("\"{$screenTitle}\": Image Carousel has no images.");
                    }
                    foreach ($images as $i => $img) {
                        if (empty(trim($img['src'] ?? $img['image_url'] ?? ''))) {
                            throw new \Exception(
                                "\"{$screenTitle}\": Image Carousel — image #" . ($i + 1) . " has no source."
                            );
                        }
                    }
                }

                if ($fieldType === 'embedded_link' && empty(trim($field['url'] ?? ''))) {
                    throw new \Exception(
                        "\"{$screenTitle}\": Embedded Link has an empty URL."
                    );
                }
            }
        }
    }

    protected function validateRichTextRules(array $screen, int $screenIndex, int $flowId): void
    {
        $fields = $screen['fields'] ?? [];
        if (empty($fields)) {
            return;
        }

        $richTextCount      = 0;
        $footerCount        = 0;
        $otherComponentCount = 0;
        $otherTypes         = [];

        foreach ($fields as $field) {
            $t = $field['type'] ?? 'unknown';
            if ($t === 'richtext')       { $richTextCount++; }
            elseif ($t === 'navigate')   { $footerCount++; }
            else                          { $otherComponentCount++; $otherTypes[] = $t; }
        }

        if ($richTextCount === 0) {
            return;
        }

        if ($richTextCount > 1) {
            throw new \Exception("Screen {$screenIndex}: RichText can only appear once per screen");
        }

        if ($otherComponentCount > 0) {
            throw new \Exception(
                "Screen {$screenIndex}: RichText can only be paired with Footer. "
                . 'Incompatible components: ' . implode(', ', $otherTypes)
            );
        }
    }
}