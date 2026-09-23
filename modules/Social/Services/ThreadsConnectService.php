<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class ThreadsConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.threads.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.threads.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.threads.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Threads Social OAuth is not configured.');
        }

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(',', $this->scopes()),
            'response_type' => 'code',
            'state' => $state,
        ]);

        $base = rtrim((string) config('social.providers.threads.oauth.authorize_url'), '?');

        return $base.'?'.$query;
    }

    /**
     * @return list<SocialAccount>
     */
    public function connectFromCode(int $companyId, string $code): array
    {
        $shortLived = $this->exchangeCode($code);
        $shortToken = (string) ($shortLived['access_token'] ?? '');

        if ($shortToken === '') {
            throw new RuntimeException('Threads did not return an access token.');
        }

        $longLived = $this->exchangeForLongLivedToken($shortToken);
        $accessToken = (string) ($longLived['access_token'] ?? $shortToken);
        $expiresAt = isset($longLived['expires_in'])
            ? now()->addSeconds((int) $longLived['expires_in'])
            : now()->addDays(60);

        $profile = $this->fetchProfile($accessToken);
        $userId = (string) ($profile['id'] ?? '');

        if ($userId === '') {
            throw new RuntimeException('Threads did not return a user id.');
        }

        $exists = SocialAccount::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', SocialProvider::Threads->value)
            ->where('external_id', $userId)
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
            externalId: $userId,
            name: (string) ($profile['name'] ?? $profile['username'] ?? 'Threads User'),
            username: isset($profile['username']) ? (string) $profile['username'] : null,
            avatar: data_get($profile, 'threads_profile_picture_url'),
            accessToken: $accessToken,
            expiresAt: $expiresAt,
            scopes: $this->scopes(),
            meta: [
                'threads_user_id' => $userId,
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
        $response = Http::get($this->apiBase().'/oauth/access_token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Threads token exchange failed: '.(string) (data_get($response->json(), 'error.message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get($this->apiBase().'/access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $this->clientSecret(),
            'access_token' => $shortLivedToken,
        ]);

        if (! $response->successful()) {
            return ['access_token' => $shortLivedToken];
        }

        return $response->json() ?? ['access_token' => $shortLivedToken];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchProfile(string $accessToken): array
    {
        $response = Http::get($this->apiBase().'/'.$this->graphVersion().'/me', [
            'fields' => 'id,username,name,threads_profile_picture_url',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Threads profile lookup failed: '.(string) (data_get($response->json(), 'error.message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
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
        $expiresAt,
        array $scopes,
        array $meta,
    ): SocialAccount {
        $account = SocialAccount::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', SocialProvider::Threads->value)
            ->where('external_id', $externalId)
            ->first();

        if (! $account) {
            $account = new SocialAccount([
                'company_id' => $companyId,
                'provider' => SocialProvider::Threads->value,
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
        $account->save();

        return $account;
    }

    protected function clientId(): string
    {
        return trim((string) config('social.providers.threads.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.threads.oauth.client_secret', ''));
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.threads.oauth.api_base', 'https://graph.threads.net'), '/');
    }

    protected function graphVersion(): string
    {
        return trim((string) config('social.providers.threads.oauth.graph_version', 'v1.0'), '/');
    }
}
