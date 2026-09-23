<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class YouTubeConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.youtube.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.youtube.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.youtube.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('YouTube Social OAuth is not configured.');
        }

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        $base = rtrim((string) config('social.providers.youtube.oauth.authorize_url'), '?');

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
        $expiresAt = isset($tokenPayload['expires_in'])
            ? now()->addSeconds((int) $tokenPayload['expires_in'])
            : now()->addHour();

        if ($accessToken === '') {
            throw new RuntimeException('YouTube did not return an access token.');
        }

        $channel = $this->fetchChannel($accessToken);
        $channelId = (string) ($channel['id'] ?? '');

        if ($channelId === '') {
            throw new RuntimeException('YouTube returned no channel for this Google account.');
        }

        $exists = SocialAccount::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', SocialProvider::YouTube->value)
            ->where('external_id', $channelId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $company = \App\Models\Company::query()->find($companyId);
            $limits = app(SocialAccountPlanLimit::class);
            if ($company && ! $limits->canAdd($company, 1)) {
                throw new RuntimeException($limits->limitExceededMessage($company, 1));
            }
        }

        $snippet = (array) ($channel['snippet'] ?? []);

        $account = $this->upsertAccount(
            companyId: $companyId,
            externalId: $channelId,
            name: (string) ($snippet['title'] ?? 'YouTube Channel'),
            username: isset($snippet['customUrl']) ? ltrim((string) $snippet['customUrl'], '@') : null,
            avatar: data_get($snippet, 'thumbnails.default.url'),
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
            scopes: explode(' ', (string) ($tokenPayload['scope'] ?? implode(' ', $this->scopes()))),
            meta: [
                'channel_id' => $channelId,
                'snippet' => $snippet,
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
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'YouTube token exchange failed: '.(string) (data_get($response->json(), 'error_description') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchChannel(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->apiBase().'/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'YouTube channel lookup failed: '.(string) (data_get($response->json(), 'error.message') ?? $response->body())
            );
        }

        $items = (array) data_get($response->json(), 'items', []);

        return (array) ($items[0] ?? []);
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
            ->where('provider', SocialProvider::YouTube->value)
            ->where('external_id', $externalId)
            ->first();

        if (! $account) {
            $account = new SocialAccount([
                'company_id' => $companyId,
                'provider' => SocialProvider::YouTube->value,
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

        if ($refreshToken) {
            $account->setRefreshToken($refreshToken);
        }

        $account->save();

        return $account;
    }

    protected function clientId(): string
    {
        return trim((string) config('social.providers.youtube.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.youtube.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.youtube.oauth.token_url', 'https://oauth2.googleapis.com/token');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.youtube.oauth.api_base', 'https://www.googleapis.com'), '/');
    }
}
