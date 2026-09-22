<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class InstagramConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.instagram.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.instagram.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.instagram.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Instagram Social OAuth is not configured.');
        }

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes()),
        ]);

        return 'https://www.facebook.com/'.$this->graphVersion().'/dialog/oauth?'.$query;
    }

    /**
     * Connect Instagram Professional accounts linked to the user's Facebook Pages.
     *
     * @return list<SocialAccount>
     */
    public function connectFromCode(int $companyId, string $code): array
    {
        $shortLived = $this->exchangeCode($code);
        $userToken = $this->exchangeForLongLivedToken($shortLived['access_token']);
        $expiresAt = isset($shortLived['expires_in'])
            ? now()->addSeconds((int) $shortLived['expires_in'])
            : now()->addDays(60);

        $pages = $this->fetchPagesWithInstagram($userToken);

        $candidatePages = [];
        foreach ($pages as $page) {
            $igId = (string) data_get($page, 'instagram_business_account.id', '');
            $pageToken = (string) data_get($page, 'access_token', '');
            if ($igId === '' || $pageToken === '') {
                continue;
            }
            $candidatePages[] = $page;
        }

        $newCount = 0;
        foreach ($candidatePages as $page) {
            $igId = (string) data_get($page, 'instagram_business_account.id', '');
            $exists = SocialAccount::withTrashed()
                ->where('company_id', $companyId)
                ->where('provider', SocialProvider::Instagram->value)
                ->where('external_id', $igId)
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

        foreach ($candidatePages as $page) {
            $igId = (string) data_get($page, 'instagram_business_account.id', '');
            $pageToken = (string) data_get($page, 'access_token', '');
            $pageId = (string) data_get($page, 'id', '');

            $account = SocialAccount::withTrashed()->firstOrNew([
                'company_id' => $companyId,
                'provider' => SocialProvider::Instagram->value,
                'external_id' => $igId,
            ]);

            $account->name = data_get($page, 'instagram_business_account.username')
                ?: data_get($page, 'name');
            $account->username = data_get($page, 'instagram_business_account.username');
            $account->avatar = data_get($page, 'instagram_business_account.profile_picture_url');
            $account->scopes = $this->scopes();
            $account->meta = [
                'page_id' => $pageId,
                'instagram_account_id' => $igId,
                'page_name' => data_get($page, 'name'),
            ];
            $account->status = 'active';
            $account->token_expires_at = $expiresAt;
            $account->deleted_at = null;
            // Publishing uses the Page token for IG content publish.
            $account->setAccessToken($pageToken);
            $account->setRefreshToken(null);
            $account->save();

            $accounts[] = $account;
        }

        if ($accounts === []) {
            throw new RuntimeException('No Instagram Professional accounts were linked to your Facebook Pages.');
        }

        return $accounts;
    }

    /**
     * @return array{access_token: string, expires_in?: int}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->get(
            'https://graph.facebook.com/'.$this->graphVersion().'/oauth/access_token',
            [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ]
        );

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException(
                'Instagram token exchange failed: '.($response->json('error.message') ?? $response->body())
            );
        }

        return $response->json();
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): string
    {
        $response = Http::get(
            'https://graph.facebook.com/'.$this->graphVersion().'/oauth/access_token',
            [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'fb_exchange_token' => $shortLivedToken,
            ]
        );

        if ($response->successful() && $response->json('access_token')) {
            return (string) $response->json('access_token');
        }

        return $shortLivedToken;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchPagesWithInstagram(string $userAccessToken): array
    {
        $response = Http::withToken($userAccessToken)->get(
            'https://graph.facebook.com/'.$this->graphVersion().'/me/accounts',
            [
                'fields' => 'id,name,access_token,instagram_business_account{id,username,profile_picture_url}',
                'limit' => 100,
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to list Pages for Instagram: '.($response->json('error.message') ?? $response->body())
            );
        }

        $pages = data_get($response->json(), 'data', []);

        return is_array($pages) ? array_values($pages) : [];
    }

    protected function clientId(): string
    {
        return (string) config('social.providers.instagram.oauth.client_id', '');
    }

    protected function clientSecret(): string
    {
        return (string) config('social.providers.instagram.oauth.client_secret', '');
    }

    protected function graphVersion(): string
    {
        return (string) config('social.providers.instagram.oauth.graph_version', 'v21.0');
    }
}
