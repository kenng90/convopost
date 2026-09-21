<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class LinkedInConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.linkedin.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.linkedin.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.linkedin.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('LinkedIn Social OAuth is not configured.');
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
        ]);

        return 'https://www.linkedin.com/oauth/v2/authorization?'.$query;
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
            : now()->addDays(60);

        if ($accessToken === '') {
            throw new RuntimeException('LinkedIn did not return an access token.');
        }

        $organizations = $this->fetchOrganizations($accessToken);
        $accounts = [];

        foreach ($organizations as $org) {
            $externalId = (string) data_get($org, 'id', '');
            if ($externalId === '') {
                continue;
            }

            $accounts[] = $this->upsertAccount(
                companyId: $companyId,
                externalId: $externalId,
                name: (string) data_get($org, 'name', 'LinkedIn Page'),
                username: null,
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresAt: $expiresAt,
                meta: [
                    'type' => 'organization',
                    'organization_urn' => 'urn:li:organization:'.$externalId,
                ],
            );
        }

        if ($accounts === []) {
            $profile = $this->fetchMemberProfile($accessToken);
            $externalId = (string) data_get($profile, 'sub', data_get($profile, 'id', ''));

            if ($externalId === '') {
                throw new RuntimeException('LinkedIn returned no organizations or member profile to connect.');
            }

            $accounts[] = $this->upsertAccount(
                companyId: $companyId,
                externalId: $externalId,
                name: (string) data_get($profile, 'name', data_get($profile, 'localizedFirstName', 'LinkedIn Profile')),
                username: data_get($profile, 'email'),
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresAt: $expiresAt,
                meta: [
                    'type' => 'member',
                    'member_urn' => 'urn:li:person:'.$externalId,
                ],
            );
        }

        return $accounts;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException(
                'LinkedIn token exchange failed: '.($response->json('error_description') ?? $response->body())
            );
        }

        return $response->json();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function fetchOrganizations(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(
            'https://api.linkedin.com/v2/organizationAcls',
            [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'state' => 'APPROVED',
                'projection' => '(elements*(organization~(id,localizedName)))',
            ]
        );

        if (! $response->successful()) {
            return [];
        }

        $organizations = [];

        foreach (data_get($response->json(), 'elements', []) as $element) {
            $id = data_get($element, 'organization~.id', data_get($element, 'organization.id'));
            $name = data_get($element, 'organization~.localizedName', data_get($element, 'organization.localizedName'));

            if ($id) {
                $organizations[] = [
                    'id' => (string) $id,
                    'name' => is_string($name) ? $name : 'LinkedIn Page',
                ];
            }
        }

        return $organizations;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchMemberProfile(string $accessToken): array
    {
        $userInfo = Http::withToken($accessToken)->get('https://api.linkedin.com/v2/userinfo');

        if ($userInfo->successful()) {
            return $userInfo->json() ?: [];
        }

        $me = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->get('https://api.linkedin.com/v2/me');

        return $me->successful() ? ($me->json() ?: []) : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function upsertAccount(
        int $companyId,
        string $externalId,
        string $name,
        ?string $username,
        string $accessToken,
        ?string $refreshToken,
        $expiresAt,
        array $meta,
    ): SocialAccount {
        $account = SocialAccount::withTrashed()->firstOrNew([
            'company_id' => $companyId,
            'provider' => SocialProvider::LinkedIn->value,
            'external_id' => $externalId,
        ]);

        $account->name = $name;
        $account->username = $username;
        $account->scopes = $this->scopes();
        $account->meta = $meta;
        $account->status = 'active';
        $account->token_expires_at = $expiresAt;
        $account->deleted_at = null;
        $account->setAccessToken($accessToken);
        $account->setRefreshToken($refreshToken);
        $account->save();

        return $account;
    }

    protected function clientId(): string
    {
        return (string) config('social.providers.linkedin.oauth.client_id', '');
    }

    protected function clientSecret(): string
    {
        return (string) config('social.providers.linkedin.oauth.client_secret', '');
    }
}
