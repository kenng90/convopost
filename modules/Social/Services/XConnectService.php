<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class XConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.x.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.x.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.x.scopes', [])));
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('X Social OAuth is not configured.');
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        $base = rtrim((string) config('social.providers.x.oauth.authorize_url'), '?');

        return $base.'?'.$query;
    }

    /**
     * @return list<SocialAccount>
     */
    public function connectFromCode(int $companyId, string $code, string $codeVerifier): array
    {
        $tokenPayload = $this->exchangeCode($code, $codeVerifier);
        $accessToken = (string) ($tokenPayload['access_token'] ?? '');
        $refreshToken = isset($tokenPayload['refresh_token']) ? (string) $tokenPayload['refresh_token'] : null;
        $expiresAt = isset($tokenPayload['expires_in'])
            ? now()->addSeconds((int) $tokenPayload['expires_in'])
            : now()->addHours(2);

        if ($accessToken === '') {
            throw new RuntimeException('X did not return an access token.');
        }

        $profile = $this->fetchMe($accessToken);
        $userId = (string) ($profile['id'] ?? '');

        if ($userId === '') {
            throw new RuntimeException('X did not return a user id.');
        }

        $exists = SocialAccount::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', SocialProvider::X->value)
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
            name: (string) ($profile['name'] ?? $profile['username'] ?? 'X User'),
            username: isset($profile['username']) ? (string) $profile['username'] : null,
            avatar: data_get($profile, 'profile_image_url'),
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
            scopes: explode(' ', (string) ($tokenPayload['scope'] ?? implode(' ', $this->scopes()))),
            meta: [
                'user_id' => $userId,
                'profile' => $profile,
            ],
        );

        return [$account];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exchangeCode(string $code, string $codeVerifier): array
    {
        $response = Http::withBasicAuth($this->clientId(), $this->clientSecret())
            ->asForm()
            ->post($this->tokenUrl(), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'code_verifier' => $codeVerifier,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'X token exchange failed: '.(string) (data_get($response->json(), 'error_description') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchMe(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->apiBase().'/2/users/me', [
                'user.fields' => 'id,name,username,profile_image_url',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'X profile lookup failed: '.(string) (data_get($response->json(), 'detail') ?? data_get($response->json(), 'title') ?? $response->body())
            );
        }

        return (array) data_get($response->json(), 'data', []);
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
            ->where('provider', SocialProvider::X->value)
            ->where('external_id', $externalId)
            ->first();

        if (! $account) {
            $account = new SocialAccount([
                'company_id' => $companyId,
                'provider' => SocialProvider::X->value,
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
        return trim((string) config('social.providers.x.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.x.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.x.oauth.token_url', 'https://api.twitter.com/2/oauth2/token');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.x.oauth.api_base', 'https://api.twitter.com'), '/');
    }
}
