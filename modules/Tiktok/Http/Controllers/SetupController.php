<?php

namespace Modules\Tiktok\Http\Controllers;

use App\Enums\MessagingChannelType;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Tiktok\Messaging\TiktokAuthClient;
use Modules\Tiktok\Messaging\TiktokClient;
use Modules\Tiktok\Messaging\TiktokWebhookSubscriber;

class SetupController extends Controller
{
    public function index(
        PlanUsageLimit $planUsageLimit,
        PlanEntitlementResolver $entitlements,
        TiktokWebhookSubscriber $webhooks,
        TiktokAuthClient $auth,
    ) {
        $user = auth()->user();

        if ($user->hasRole('admin') && ! session()->has('impersonate')) {
            return redirect()->route('whatsapp.setup');
        }

        $plan = $planUsageLimit->resolvePlanForUser($user);
        if (! $plan || ! $entitlements->hasCapability($plan, 'inbox_tiktok')) {
            return redirect()
                ->route('plans.current')
                ->withError(__('This feature is not included in your plan.'));
        }

        $company = $this->getCompany();
        $connection = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Tiktok->value)
            ->first();

        $companyToken = $connection?->webhook_token ?? auth()->user()->createToken('tiktok-webhook')->plainTextToken;
        $callbackToken = $webhooks->resolveCallbackToken($companyToken);

        return view('tiktok::setup', [
            'company' => $company,
            'webhookUrl' => route('messaging.webhook.receive', [
                'channel' => MessagingChannelType::Tiktok->value,
                'token' => $callbackToken,
            ]),
            'verifyToken' => $companyToken,
            'callbackToken' => $callbackToken,
            'isConnected' => $company->getConfig('tiktok_connected', 'no') === 'yes',
            'connection' => $connection,
            'oauthEnabled' => $auth->hasAppCredentials(),
            'oauthRedirectUri' => $auth->hasAppCredentials() ? $auth->redirectUri() : '',
        ]);
    }

    public function redirect(TiktokAuthClient $auth): RedirectResponse
    {
        if (auth()->user()->hasRole('admin') && ! session()->has('impersonate')) {
            return redirect()->route('whatsapp.setup');
        }

        if (! $auth->hasAppCredentials()) {
            return redirect()->route('tiktok.setup')
                ->withError(__('TikTok app_id and app_secret are not configured. Add TIKTOK_APP_ID and TIKTOK_APP_SECRET, or paste tokens below.'));
        }

        $company = $this->getCompany();
        $state = $this->makeOauthState((int) $company->id);

        return redirect()->away($auth->authorizeUrl($state));
    }

    public function callback(
        Request $request,
        TiktokAuthClient $auth,
        TiktokClient $client,
        ChannelConnectionService $connections,
        TiktokWebhookSubscriber $webhooks,
    ): RedirectResponse {
        if (auth()->user()->hasRole('admin') && ! session()->has('impersonate')) {
            return redirect()->route('whatsapp.setup');
        }

        if ($request->filled('error')) {
            return redirect()->route('tiktok.setup')
                ->withError(__('TikTok connection was cancelled: :error', [
                    'error' => (string) $request->query('error_description', $request->query('error')),
                ]));
        }

        if (! $this->oauthStateIsValid($request)) {
            return redirect()->route('tiktok.setup')
                ->withError(__('TikTok connection failed because the OAuth state was invalid. Please try again.'));
        }

        $code = (string) $request->query('code', $request->query('auth_code', ''));
        if ($code === '') {
            return redirect()->route('tiktok.setup')
                ->withError(__('TikTok did not return an authorization code.'));
        }

        $exchanged = $client->exchangeAuthorizationCode($code, $auth->redirectUri());
        if (! $exchanged['ok']) {
            return redirect()->route('tiktok.setup')
                ->withError(__('TikTok token exchange failed: :message', [
                    'message' => $exchanged['message'] !== '' ? $exchanged['message'] : __('Unknown error'),
                ]));
        }

        $businessId = (string) ($exchanged['data']['open_id'] ?? '');
        $accessToken = (string) ($exchanged['data']['access_token'] ?? '');
        if ($businessId === '' || $accessToken === '') {
            return redirect()->route('tiktok.setup')
                ->withError(__('TikTok did not return a Business Account id and access token.'));
        }

        $expiresIn = (int) ($exchanged['data']['expires_in'] ?? 0);
        $refreshExpiresIn = (int) ($exchanged['data']['refresh_token_expires_in'] ?? 0);

        return $this->persistConnection(
            $connections,
            $webhooks,
            $client,
            $this->getCompany(),
            $businessId,
            $accessToken,
            array_filter([
                'refresh_token' => $exchanged['data']['refresh_token'] ?? null,
                'scope' => $exchanged['data']['scope'] ?? null,
                'access_token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn)->toIso8601String() : null,
                'refresh_token_expires_at' => $refreshExpiresIn > 0 ? now()->addSeconds($refreshExpiresIn)->toIso8601String() : null,
            ]),
            $this->webhookTokenForCompany($this->getCompany()->id),
        );
    }

    public function store(
        Request $request,
        ChannelConnectionService $connections,
        TiktokWebhookSubscriber $webhooks,
        TiktokClient $client,
    ): RedirectResponse {
        $validated = $request->validate([
            'business_id' => 'required|string',
            'access_token' => 'required|string',
            'refresh_token' => 'nullable|string',
            'webhook_token' => 'required|string',
        ]);

        return $this->persistConnection(
            $connections,
            $webhooks,
            $client,
            $this->getCompany(),
            $validated['business_id'],
            $validated['access_token'],
            array_filter([
                'refresh_token' => $validated['refresh_token'] ?? null,
            ]),
            $validated['webhook_token'],
        );
    }

    private function persistConnection(
        ChannelConnectionService $connections,
        TiktokWebhookSubscriber $webhooks,
        TiktokClient $client,
        Company $company,
        string $businessId,
        string $accessToken,
        array $extra,
        string $webhookToken,
    ): RedirectResponse {
        $company->setConfig('tiktok_business_id', $businessId);
        $company->setConfig('tiktok_access_token', $accessToken);

        $connection = $connections->upsertTiktokConnection(
            $company,
            $businessId,
            $accessToken,
            $extra,
        );

        $connections->storeWebhookToken($connection, $webhookToken);
        $connection->refresh();

        $subscription = $webhooks->subscribeForConnection($connection);
        $commentToDm = $client->updateCommentToMessage($connection->accessToken(), $businessId);

        $redirect = redirect()->route('tiktok.setup')
            ->withStatus(__('TikTok Business Messaging connected successfully.'));

        $warnings = [];

        if ($subscription['attempted'] && ! $subscription['ok']) {
            $warnings[] = __('Connection saved, but TikTok webhook subscribe failed: :message', [
                'message' => $subscription['message'],
            ]);
        } elseif (! $subscription['attempted']) {
            $warnings[] = $subscription['message'];
        }

        if (! $commentToDm['ok']) {
            $warnings[] = __('Comment-to-Message could not be enabled: :message', [
                'message' => $commentToDm['message'] !== '' ? $commentToDm['message'] : __('Unknown error'),
            ]);
        }

        if ($warnings !== []) {
            return $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    private function webhookTokenForCompany(int $companyId): string
    {
        $existing = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('channel', MessagingChannelType::Tiktok->value)
            ->first();

        if ($existing && filled($existing->webhook_token)) {
            return (string) $existing->webhook_token;
        }

        return auth()->user()->createToken('tiktok-webhook')->plainTextToken;
    }

    private function makeOauthState(int $companyId): string
    {
        $payload = $companyId.'.'.Str::random(40);
        $state = $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));

        session([
            'tiktok_oauth_state' => $state,
            'tiktok_oauth_company_id' => $companyId,
        ]);

        return $state;
    }

    private function oauthStateIsValid(Request $request): bool
    {
        $state = (string) $request->query('state', '');
        $expected = (string) $request->session()->pull('tiktok_oauth_state', '');
        $companyId = (int) $request->session()->pull('tiktok_oauth_company_id', 0);

        return $state !== ''
            && $expected !== ''
            && hash_equals($expected, $state)
            && $companyId === (int) $this->getCompany()?->id;
    }
}
