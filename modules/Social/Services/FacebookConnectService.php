<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class FacebookConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.facebook.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.facebook.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.facebook.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Facebook Social OAuth is not configured.');
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
     * Exchange the OAuth code and upsert Facebook Page accounts for the company.
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

        $pages = $this->fetchPages($userToken);

        if ($pages === []) {
            throw new RuntimeException('No Facebook Pages were returned for this Facebook user.');
        }

        $accounts = [];

        foreach ($pages as $page) {
            $externalId = (string) data_get($page, 'id', '');
            $pageToken = (string) data_get($page, 'access_token', '');

            if ($externalId === '' || $pageToken === '') {
                continue;
            }

            $account = SocialAccount::withTrashed()->firstOrNew([
                'company_id' => $companyId,
                'provider' => SocialProvider::Facebook->value,
                'external_id' => $externalId,
            ]);

            $account->name = data_get($page, 'name');
            $account->username = data_get($page, 'name');
            $account->avatar = data_get($page, 'picture.data.url');
            $account->scopes = $this->scopes();
            $account->meta = [
                'page_id' => $externalId,
                'category' => data_get($page, 'category'),
                'tasks' => data_get($page, 'tasks', []),
            ];
            $account->status = 'active';
            $account->token_expires_at = $expiresAt;
            $account->deleted_at = null;
            $account->setAccessToken($pageToken);
            $account->setRefreshToken(null);
            $account->save();

            $accounts[] = $account;
        }

        if ($accounts === []) {
            throw new RuntimeException('Facebook Pages were found but none included an access token.');
        }

        return $accounts;
    }

    /**
     * @return array{access_token: string, expires_in?: int, token_type?: string}
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
                'Facebook token exchange failed: '.($response->json('error.message') ?? $response->body())
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
    public function fetchPages(string $userAccessToken): array
    {
        $response = Http::withToken($userAccessToken)->get(
            'https://graph.facebook.com/'.$this->graphVersion().'/me/accounts',
            [
                'fields' => 'id,name,access_token,category,tasks,picture{url}',
                'limit' => 100,
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to list Facebook Pages: '.($response->json('error.message') ?? $response->body())
            );
        }

        $pages = data_get($response->json(), 'data', []);

        return is_array($pages) ? array_values($pages) : [];
    }

    protected function clientId(): string
    {
        return (string) config('social.providers.facebook.oauth.client_id', '');
    }

    protected function clientSecret(): string
    {
        return (string) config('social.providers.facebook.oauth.client_secret', '');
    }

    protected function graphVersion(): string
    {
        return (string) config('social.providers.facebook.oauth.graph_version', 'v21.0');
    }
}
