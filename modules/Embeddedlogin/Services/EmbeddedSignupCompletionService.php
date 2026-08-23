<?php

namespace Modules\Embeddedlogin\Services;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\User;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\Messaging\MetaPageLinkService;
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

        try {
            app(\App\Services\Onboarding\VerticalGoLiveService::class)->publishPendingMetaAssets($company);
        } catch (\Throwable $e) {
            Log::warning('Embedded signup: vertical go-live Meta publish failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }

        $connected = ['whatsapp' => true];

        if ($session->isOmnichannel()) {
            $webhookToken = $this->resolveWebhookToken($user, $company);
            $pages = $this->resolvePagesForOmnichannel($accessToken, $session, (string) $wabaId);

            if ($pages === []) {
                Log::warning('Embedded signup: omnichannel completed without a Facebook Page on the token', [
                    'company_id' => $company->id,
                    'session_page_id' => $session->pageId,
                ]);
            }

            foreach ($pages as $page) {
                $connected = array_merge($connected, $this->provisionPageMessagingChannels(
                    $company,
                    $page['id'],
                    $page['instagram_account_id'],
                    $accessToken,
                    $webhookToken,
                    $page['access_token'] ?? null,
                ));
            }
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
     * Embedded Signup returns a customer business token, not a user token.
     * GET /{page-id} therefore fails with pages_read_engagement. Discover Pages
     * from /me/accounts and the business portfolio instead.
     *
     * @return list<array{id: string, instagram_account_id: ?string, access_token: ?string}>
     */
    private function resolvePagesForOmnichannel(
        string $accessToken,
        EmbeddedSignupSession $session,
        string $wabaId,
    ): array {
        $pages = [];

        $this->mergePagesFromGraph($pages, $accessToken, '/me/accounts', 'me_accounts');

        $debug = $this->debugToken($accessToken);
        $userId = (string) data_get($debug, 'data.user_id', '');
        if ($userId !== '') {
            $this->mergePagesFromGraph($pages, $accessToken, '/'.$userId.'/accounts', 'user_accounts');
        }

        foreach ($this->candidateBusinessIds($session, $accessToken, $wabaId) as $businessId) {
            $this->mergePagesFromGraph($pages, $accessToken, '/'.$businessId.'/owned_pages', 'owned_pages');
            $this->mergePagesFromGraph($pages, $accessToken, '/'.$businessId.'/client_pages', 'client_pages');
        }

        if ($session->pageId) {
            $existing = $pages[$session->pageId] ?? null;
            $pages[$session->pageId] = [
                'id' => $session->pageId,
                'instagram_account_id' => $existing['instagram_account_id']
                    ?? $session->instagramAccountId,
                'access_token' => $existing['access_token'] ?? null,
            ];
        } elseif ($session->instagramAccountId) {
            foreach ($pages as $pageId => $page) {
                if (empty($page['instagram_account_id'])) {
                    $pages[$pageId]['instagram_account_id'] = $session->instagramAccountId;
                }
            }
        }

        Log::info('Embedded signup: resolved Facebook Pages', [
            'count' => count($pages),
            'session_page_id' => $session->pageId,
            'token_type' => data_get($debug, 'data.type'),
            'pages' => array_values(array_map(fn (array $page) => [
                'id' => $page['id'],
                'has_token' => ! empty($page['access_token']),
                'instagram_account_id' => $page['instagram_account_id'],
            ], $pages)),
        ]);

        uasort($pages, function (array $left, array $right) {
            return (int) empty($left['instagram_account_id']) <=> (int) empty($right['instagram_account_id']);
        });

        return array_values($pages);
    }

    /**
     * @param  array<string, array{id: string, instagram_account_id: mixed, access_token: mixed}>  $pages
     */
    private function mergePagesFromGraph(array &$pages, string $accessToken, string $path, string $source): void
    {
        $response = Http::withToken($accessToken)->get($this->graphUrl().$path, [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
        ]);

        if (! $response->successful()) {
            Log::info('Embedded signup: page discovery failed', [
                'source' => $source,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $rows = $response->json('data') ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $account) {
            if (! is_array($account)) {
                continue;
            }

            $pageId = (string) ($account['id'] ?? '');
            if ($pageId === '') {
                continue;
            }

            $token = $account['access_token'] ?? null;
            $existing = $pages[$pageId] ?? null;

            $pages[$pageId] = [
                'id' => $pageId,
                'instagram_account_id' => data_get($account, 'instagram_business_account.id')
                    ?: ($existing['instagram_account_id'] ?? null),
                'access_token' => (is_string($token) && $token !== '')
                    ? $token
                    : ($existing['access_token'] ?? null),
            ];
        }

        Log::info('Embedded signup: page discovery', [
            'source' => $source,
            'count' => count($rows),
            'page_ids' => array_values(array_filter(array_map(
                fn ($row) => is_array($row) ? ($row['id'] ?? null) : null,
                $rows,
            ))),
        ]);
    }

    /**
     * @return list<string>
     */
    private function candidateBusinessIds(EmbeddedSignupSession $session, string $accessToken, string $wabaId): array
    {
        $ids = [];

        if (is_string($session->businessId) && $session->businessId !== '') {
            $ids[] = $session->businessId;
        }

        if ($wabaId !== '') {
            $waba = Http::withToken($accessToken)->get($this->graphUrl().'/'.$wabaId, [
                'fields' => 'owner_business_info,on_behalf_of_business_info',
            ]);

            if ($waba->successful()) {
                foreach (['owner_business_info.id', 'on_behalf_of_business_info.id'] as $path) {
                    $businessId = (string) data_get($waba->json(), $path, '');
                    if ($businessId !== '') {
                        $ids[] = $businessId;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, bool>
     */
    private function provisionPageMessagingChannels(
        Company $company,
        string $pageId,
        ?string $instagramAccountId,
        string $accessToken,
        string $webhookToken,
        ?string $pageAccessToken = null,
    ): array {
        $connected = [
            'instagram' => false,
            'messenger' => false,
        ];

        if ($pageId === '') {
            return $connected;
        }

        $pageToken = is_string($pageAccessToken) && $pageAccessToken !== '' ? $pageAccessToken : '';

        if ($pageToken === '') {
            Log::warning('Embedded signup: no Page access token from Graph; using Embedded Signup token to subscribe Page webhooks', [
                'page_id' => $pageId,
                'company_id' => $company->id,
            ]);
            $pageToken = $accessToken;
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
            'subscribed_fields' => MetaPageLinkService::PAGE_SUBSCRIBED_FIELDS,
        ]);

        if (! $response->successful()) {
            Log::warning('Embedded signup: page webhook subscription failed', [
                'page_id' => $pageId,
                'body' => $response->body(),
            ]);
        }
    }
}
