<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class GbpConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.gbp.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.gbp.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.gbp.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Business Profile OAuth is not configured.');
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

        $base = rtrim((string) config('social.providers.gbp.oauth.authorize_url'), '?');

        return $base.'?'.$query;
    }

    /**
     * Connect one Social account per GBP location.
     *
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
            throw new RuntimeException('Google Business Profile did not return an access token.');
        }

        $locations = $this->fetchLocations($accessToken);

        if ($locations === []) {
            throw new RuntimeException('No Google Business Profile locations found for this Google account.');
        }

        $newCount = 0;
        foreach ($locations as $location) {
            $externalId = (string) ($location['external_id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $exists = SocialAccount::withTrashed()
                ->where('company_id', $companyId)
                ->where('provider', SocialProvider::Gbp->value)
                ->where('external_id', $externalId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $exists) {
                $newCount++;
            }
        }

        if ($newCount > 0) {
            $company = \App\Models\Company::query()->find($companyId);
            $limits = app(SocialAccountPlanLimit::class);
            if ($company && ! $limits->canAdd($company, $newCount)) {
                throw new RuntimeException($limits->limitExceededMessage($company, $newCount));
            }
        }

        $accounts = [];
        foreach ($locations as $location) {
            $externalId = (string) ($location['external_id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $accounts[] = $this->upsertAccount(
                companyId: $companyId,
                externalId: $externalId,
                name: (string) ($location['title'] ?? 'Google Business location'),
                username: null,
                avatar: null,
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresAt: $expiresAt,
                scopes: explode(' ', (string) ($tokenPayload['scope'] ?? implode(' ', $this->scopes()))),
                meta: [
                    'location_name' => $location['name'],
                    'account_name' => $location['account_name'],
                    'title' => $location['title'],
                ],
            );
        }

        return $accounts;
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
                'Google Business Profile token exchange failed: '.(string) (data_get($response->json(), 'error_description') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return list<array{external_id: string, name: string, account_name: string, title: string}>
     */
    protected function fetchLocations(string $accessToken): array
    {
        $accountsResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->accountManagementBase().'/v1/accounts');

        if (! $accountsResponse->successful()) {
            throw new RuntimeException(
                'Google Business Profile accounts lookup failed: '.(string) (data_get($accountsResponse->json(), 'error.message') ?? $accountsResponse->body())
            );
        }

        $accounts = (array) data_get($accountsResponse->json(), 'accounts', []);
        $locations = [];

        foreach ($accounts as $account) {
            $accountName = (string) ($account['name'] ?? '');
            if ($accountName === '') {
                continue;
            }

            $locationsResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->get($this->businessInfoBase().'/v1/'.$accountName.'/locations', [
                    'readMask' => 'name,title',
                    'pageSize' => 100,
                ]);

            if (! $locationsResponse->successful()) {
                continue;
            }

            foreach ((array) data_get($locationsResponse->json(), 'locations', []) as $location) {
                $name = (string) ($location['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                // name is accounts/{accountId}/locations/{locationId}
                $externalId = str_replace('/', '_', $name);

                $locations[] = [
                    'external_id' => $externalId,
                    'name' => $name,
                    'account_name' => $accountName,
                    'title' => (string) ($location['title'] ?? $name),
                ];
            }
        }

        return $locations;
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
            ->where('provider', SocialProvider::Gbp->value)
            ->where('external_id', $externalId)
            ->first();

        if (! $account) {
            $account = new SocialAccount([
                'company_id' => $companyId,
                'provider' => SocialProvider::Gbp->value,
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
        return trim((string) config('social.providers.gbp.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.gbp.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.gbp.oauth.token_url', 'https://oauth2.googleapis.com/token');
    }

    protected function accountManagementBase(): string
    {
        return rtrim((string) config('social.providers.gbp.oauth.account_management_base', 'https://mybusinessaccountmanagement.googleapis.com'), '/');
    }

    protected function businessInfoBase(): string
    {
        return rtrim((string) config('social.providers.gbp.oauth.business_info_base', 'https://mybusinessbusinessinformation.googleapis.com'), '/');
    }
}
