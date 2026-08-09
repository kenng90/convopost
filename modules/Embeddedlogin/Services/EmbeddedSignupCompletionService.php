<?php

namespace Modules\Embeddedlogin\Services;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\User;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\WhatsApp\WebhookCompanyResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddedSignupCompletionService
{
    public function __construct(
        private readonly ChannelConnectionService $channelConnections,
        private readonly WebhookCompanyResolver $webhookCompanyResolver,
    ) {
    }

    public function graphUrl(): string
    {
        return 'https://graph.facebook.com/'.config('embeddedlogin.graph_version', 'v22.0');
    }

    /**
     * @return array{status: string, error?: string, hint?: string, success?: string, connected?: array<string, bool>}
     */
    public function complete(User $user, string $code, EmbeddedSignupSession $session): array
    {
        Auth::login($user);

        $accessTokenResult = $this->exchangeCodeForToken($code);

        if (! is_array($accessTokenResult) || empty($accessTokenResult['access_token'])) {
            return [
                'status' => 'error',
                'error' => 'Invalid code - Error in the Facebook App.',
                'info' => $accessTokenResult,
            ];
        }

        $accessToken = $accessTokenResult['access_token'];
        $wabaId = $session->wabaId;
        $phoneId = $session->phoneNumberId;

        if (! $wabaId || ! $phoneId) {
            $wabidResult = $this->debugToken($accessToken);
            $wabaId = $this->resolveWabidFromDebugToken($wabidResult);

            if ($wabaId === null) {
                Log::warning('Embedded signup: no WABA ID in debug_token granular_scopes', [
                    'scopes' => collect($wabidResult['data']['granular_scopes'] ?? [])->pluck('scope')->all(),
                    'flow' => $session->flow,
                ]);

                return [
                    'status' => 'error',
                    'error' => 'No WABID found. Please check the app permissions',
                    'hint' => 'Request Advanced Access for whatsapp_business_management and whatsapp_business_messaging in Meta App Review.',
                ];
            }
        }

        $phoneResult = $this->getPhoneNumbers($accessToken, $wabaId);
        $phone = (string) data_get($phoneResult, 'data.0.display_phone_number', '');

        if (! $phoneId) {
            $phoneId = data_get($phoneResult, 'data.0.id');
        }

        if (! $phoneId) {
            return ['status' => 'error', 'error' => 'Error getting phone number'];
        }

        $company = $user->getCurrentCompany();

        if ($this->webhookCompanyResolver->phoneNumberIdUsedByAnotherCompany((string) $phoneId, $company->id)) {
            Log::warning('Embedded WhatsApp signup rejected: phone number already linked to another organisation', [
                'company_id' => $company->id,
                'phone_number_id' => $phoneId,
            ]);

            return [
                'status' => 'error',
                'error' => 'This WhatsApp phone number is already connected to another organisation.',
            ];
        }

        $registerResult = $this->registerPhoneNumber($accessToken, $phoneId);
        if (! ($registerResult['success'] ?? false)) {
            return ['status' => 'error', 'error' => 'Error registering phone number'];
        }

        $subscribeResult = $this->subscribeWabaWebhooks($accessToken, $wabaId);
        if (! ($subscribeResult['success'] ?? false)) {
            return ['status' => 'error', 'error' => 'Error subscribing to the webhook'];
        }

        $this->persistWhatsappCompanyConfig($company, (string) $phoneId, (string) $wabaId, $accessToken);
        $this->channelConnections->ensureWhatsappConnection($company);

        $connected = ['whatsapp' => true];

        if ($session->isOmnichannel() && $session->pageId) {
            $webhookToken = $this->resolveWebhookToken($user, $company);
            $connected = array_merge($connected, $this->provisionMetaMessagingChannels(
                $company,
                $session,
                $accessToken,
                $webhookToken,
            ));
        }

        return [
            'status' => 'success',
            'success' => 'Business messaging assets linked successfully.',
            'connected' => $connected,
        ];
    }

    public function exchangeCodeForToken(string $code): ?array
    {
        $response = Http::get($this->graphUrl().'/oauth/access_token', [
            'client_id' => config('services.facebook.app_id'),
            'client_secret' => config('services.facebook.app_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        Log::info('Embedded signup token exchange', ['status' => $response->status()]);

        return $response->successful() ? $response->json() : null;
    }

    public function resolveWabidFromDebugToken(?array $wabidResult): ?string
    {
        $granularScopes = $wabidResult['data']['granular_scopes'] ?? [];
        $preferredScopes = ['whatsapp_business_management', 'whatsapp_business_messaging'];

        foreach ($preferredScopes as $preferredScope) {
            foreach ($granularScopes as $scope) {
                if (
                    ($scope['scope'] ?? '') === $preferredScope
                    && ! empty($scope['target_ids'])
                ) {
                    return $scope['target_ids'][0];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    private function provisionMetaMessagingChannels(
        Company $company,
        EmbeddedSignupSession $session,
        string $accessToken,
        string $webhookToken,
    ): array {
        $connected = [
            'instagram' => false,
            'messenger' => false,
        ];

        $pageId = $session->pageId;
        if (! $pageId) {
            return $connected;
        }

        $pageToken = $accessToken;
        $instagramAccountId = $session->instagramAccountId;

        $pageNode = Http::withToken($accessToken)->get($this->graphUrl().'/'.$pageId, [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
        ]);

        if ($pageNode->successful()) {
            $exchanged = (string) data_get($pageNode->json(), 'access_token', '');
            if ($exchanged !== '') {
                $pageToken = $exchanged;
            }

            $linkedIg = (string) data_get($pageNode->json(), 'instagram_business_account.id', '');
            if ($linkedIg !== '' && ($instagramAccountId === null || $instagramAccountId === '')) {
                $instagramAccountId = $linkedIg;
            }
        } else {
            Log::warning('Embedded signup: could not load Page node for token exchange', [
                'page_id' => $pageId,
                'body' => $pageNode->body(),
            ]);
        }

        $this->subscribePageWebhooks($pageToken, $pageId);

        $instagramConnection = $this->channelConnections->upsertMetaConnection(
            $company,
            MessagingChannelType::Instagram,
            $pageId,
            $pageToken,
            array_filter([
                'instagram_account_id' => $instagramAccountId,
            ]),
        );
        $this->channelConnections->storeWebhookToken($instagramConnection, $webhookToken);
        $connected['instagram'] = true;

        $company->setConfig('instagram_page_id', $pageId);
        $company->setConfig('instagram_page_access_token', $pageToken);
        if ($instagramAccountId) {
            $company->setConfig('instagram_account_id', $instagramAccountId);
        }

        $messengerConnection = $this->channelConnections->upsertMetaConnection(
            $company,
            MessagingChannelType::Messenger,
            $pageId,
            $pageToken,
        );
        $this->channelConnections->storeWebhookToken($messengerConnection, $webhookToken);
        $connected['messenger'] = true;

        $company->setConfig('messenger_page_id', $pageId);
        $company->setConfig('messenger_page_access_token', $pageToken);

        return $connected;
    }

    private function persistWhatsappCompanyConfig(
        Company $company,
        string $phoneId,
        string $wabaId,
        string $accessToken,
    ): void {
        $company->setConfig('whatsapp_permanent_access_token', $accessToken);
        $company->setConfig('whatsapp_business_account_id', $wabaId);
        $company->setConfig('whatsapp_phone_number_id', $phoneId);
        $company->setConfig('whatsapp_webhook_verified', 'yes');
        $company->setConfig('whatsapp_settings_done', 'yes');
        $company->setConfig('embedded_signup_flow', 'completed');
    }

    private function resolveWebhookToken(User $user, Company $company): string
    {
        $existing = (string) $company->getConfig('plain_token', '');
        if ($existing !== '') {
            return $existing;
        }

        $token = $user->createToken('Messaging webhook '.$company->id);
        $parts = explode('|', $token->plainTextToken);
        $plain = $parts[1] ?? $token->plainTextToken;
        $company->setConfig('plain_token', $plain);

        return $plain;
    }

    private function debugToken(string $accessToken): ?array
    {
        $appId = config('services.facebook.app_id');
        $appSecret = config('services.facebook.app_secret');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$appId.'|'.$appSecret,
        ])->get($this->graphUrl().'/debug_token', [
            'input_token' => $accessToken,
        ]);

        return $response->successful() ? $response->json() : null;
    }

    private function getPhoneNumbers(string $accessToken, string $wabaId): ?array
    {
        $response = Http::withToken($accessToken)->get($this->graphUrl().'/'.$wabaId.'/phone_numbers');

        return $response->successful() ? $response->json() : null;
    }

    private function registerPhoneNumber(string $accessToken, string $phoneId): ?array
    {
        $response = Http::withToken($accessToken)->post($this->graphUrl().'/'.$phoneId.'/register', [
            'messaging_product' => 'whatsapp',
            'pin' => '212834',
        ]);

        return $response->successful() ? $response->json() : null;
    }

    private function subscribeWabaWebhooks(string $accessToken, string $wabaId): ?array
    {
        $response = Http::withToken($accessToken)->post($this->graphUrl().'/'.$wabaId.'/subscribed_apps');

        return $response->successful() ? $response->json() : null;
    }

    private function subscribePageWebhooks(string $accessToken, string $pageId): void
    {
        $response = Http::withToken($accessToken)->post($this->graphUrl().'/'.$pageId.'/subscribed_apps', [
            'subscribed_fields' => ['messages', 'messaging_postbacks'],
        ]);

        if (! $response->successful()) {
            Log::warning('Embedded signup: page webhook subscription failed', [
                'page_id' => $pageId,
                'body' => $response->body(),
            ]);
        }
    }
}
