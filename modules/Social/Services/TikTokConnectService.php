<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class TikTokConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientKey() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.tiktok.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.tiktok.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.tiktok.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('TikTok Social OAuth is not configured.');
        }

        $query = http_build_query([
            'client_key' => $this->clientKey(),
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes()),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);

        $base = rtrim((string) config('social.providers.tiktok.oauth.authorize_url'), '?');

        return $base.'?'.$query;
    }

    /**
     * @return list<SocialAccount>
     */
    public function connectFromCode(int $companyId, string $code): array
    {
        $tokenPayload = $this->exchangeCode($code);
        $accessToken = (string) ($tokenPayload['access_token'] ?? '');
        $refreshToken = isset($tokenPayload['refresh_token']) ? (string) $tokenPayload['refresh_token'] : null;
        $openId = (string) ($tokenPayload['open_id'] ?? '');
        $expiresAt = isset($tokenPayload['expires_in'])
            ? now()->addSeconds((int) $tokenPayload['expires_in'])
            : now()->addDay();

        if ($accessToken === '' || $openId === '') {
            throw new RuntimeException('TikTok did not return an access token and open_id.');
        }

        $profile = $this->fetchUserInfo($accessToken);

        $exists = SocialAccount::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', SocialProvider::TikTok->value)
            ->where('external_id', $openId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $company = \App\Models\Company::query()->find($companyId);
            $limits = app(SocialAccountPlanLimit::class);
            if ($company && ! $limits->canAdd($company, 1)) {
                throw new RuntimeException($limits->limitExceededMessage($company, 1));
            }
        }

        $account = $this->upsertAccount(
            companyId: $companyId,
            externalId: $openId,
            name: (string) data_get($profile, 'display_name', 'TikTok Creator'),
            username: data_get($profile, 'username'),
            avatar: data_get($profile, 'avatar_url'),
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
            scopes: explode(',', (string) ($tokenPayload['scope'] ?? implode(',', $this->scopes()))),
            meta: [
                'open_id' => $openId,
                'union_id' => data_get($tokenPayload, 'union_id'),
                'profile' => $profile,
            ],
        );

        return [$account];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_key' => $this->clientKey(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'TikTok token exchange failed: '.(string) ($response->json('error_description') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->apiBase().'/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name,username',
            ]);

        if (! $response->successful()) {
            return [];
        }

        return (array) data_get($response->json(), 'data.user', []);
    }

    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $meta
     */
    protected function upsertAccount(
        int $companyId,
        string $externalId,
        string $name,
        ?string $username,
        ?string $avatar,
        string $accessToken,
        ?string $refreshToken,
        $expiresAt,
        array $scopes,
        array $meta,
    ): SocialAccount {
        $account = SocialAccount::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', SocialProvider::TikTok->value)
            ->where('external_id', $externalId)
            ->first();

        if (! $account) {
            $account = new SocialAccount([
                'company_id' => $companyId,
                'provider' => SocialProvider::TikTok->value,
                'external_id' => $externalId,
            ]);
        }

        if ($account->trashed()) {
            $account->restore();
        }

        $account->fill([
            'name' => $name,
            'username' => $username,
            'avatar' => $avatar,
            'token_expires_at' => $expiresAt,
            'scopes' => $scopes,
            'meta' => $meta,
            'status' => 'active',
        ]);
        $account->setAccessToken($accessToken);
        $account->setRefreshToken($refreshToken);
        $account->save();

        return $account;
    }

    protected function clientKey(): string
    {
        return trim((string) config('social.providers.tiktok.oauth.client_key', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.tiktok.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.tiktok.oauth.token_url', 'https://open.tiktokapis.com/v2/oauth/token/');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.tiktok.oauth.api_base', 'https://open.tiktokapis.com'), '/');
    }
}
