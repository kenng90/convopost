<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use RuntimeException;

class PinterestConnectService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        $configured = (string) config('social.providers.pinterest.oauth.redirect', '');

        if ($configured !== '') {
            return $configured;
        }

        return route('social.accounts.connect.pinterest.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('social.providers.pinterest.scopes', [])));
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Pinterest Social OAuth is not configured.');
        }

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes()),
            'state' => $state,
        ]);

        $base = rtrim((string) config('social.providers.pinterest.oauth.authorize_url'), '?');

        return $base.'?'.$query;
    }

    /**
     * Connect one Social account per Pinterest board (publish targets).
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
            : now()->addDays(30);

        if ($accessToken === '') {
            throw new RuntimeException('Pinterest did not return an access token.');
        }

        $profile = $this->fetchUserAccount($accessToken);
        $boards = $this->fetchBoards($accessToken);

        if ($boards === []) {
            throw new RuntimeException('Pinterest returned no boards to connect. Create a board first, then try again.');
        }

        $newCount = 0;
        foreach ($boards as $board) {
            $boardId = (string) ($board['id'] ?? '');
            if ($boardId === '') {
                continue;
            }

            $exists = SocialAccount::withTrashed()
                ->where('company_id', $companyId)
                ->where('provider', SocialProvider::Pinterest->value)
                ->where('external_id', $boardId)
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
        $username = data_get($profile, 'username');
        $avatar = data_get($profile, 'profile_image');

        foreach ($boards as $board) {
            $boardId = (string) ($board['id'] ?? '');
            if ($boardId === '') {
                continue;
            }

            $accounts[] = $this->upsertAccount(
                companyId: $companyId,
                externalId: $boardId,
                name: (string) ($board['name'] ?? 'Pinterest Board'),
                username: is_string($username) ? $username : null,
                avatar: is_string($avatar) ? $avatar : null,
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                expiresAt: $expiresAt,
                scopes: explode(',', (string) ($tokenPayload['scope'] ?? implode(',', $this->scopes()))),
                meta: [
                    'board_id' => $boardId,
                    'board' => $board,
                    'user_account' => $profile,
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
        $response = Http::withBasicAuth($this->clientId(), $this->clientSecret())
            ->asForm()
            ->post($this->tokenUrl(), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Pinterest token exchange failed: '.(string) (data_get($response->json(), 'message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchUserAccount(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->apiBase().'/v5/user_account');

        if (! $response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchBoards(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($this->apiBase().'/v5/boards', [
                'page_size' => 100,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Pinterest boards lookup failed: '.(string) (data_get($response->json(), 'message') ?? $response->body())
            );
        }

        return array_values((array) data_get($response->json(), 'items', []));
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
            ->where('provider', SocialProvider::Pinterest->value)
            ->where('external_id', $externalId)
            ->first();

        if (! $account) {
            $account = new SocialAccount([
                'company_id' => $companyId,
                'provider' => SocialProvider::Pinterest->value,
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
        return trim((string) config('social.providers.pinterest.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.pinterest.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.pinterest.oauth.token_url', 'https://api.pinterest.com/v5/oauth/token');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.pinterest.oauth.api_base', 'https://api.pinterest.com'), '/');
    }
}
