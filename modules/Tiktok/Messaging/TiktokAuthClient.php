<?php

namespace Modules\Tiktok\Messaging;

class TiktokAuthClient
{
    /**
     * @var array<int, string>
     */
    public const REQUIRED_SCOPES = [
        'user.info.basic',
        'user.info.username',
        'user.info.stats',
        'user.info.profile',
        'user.account.type',
        'user.insights',
        'message.list.read',
        'message.list.send',
        'message.list.manage',
    ];

    public function hasAppCredentials(): bool
    {
        return $this->appId() !== '' && $this->appSecret() !== '';
    }

    public function redirectUri(): string
    {
        return route('tiktok.oauth.callback');
    }

    public function authorizeUrl(string $state): string
    {
        $query = http_build_query([
            'client_key' => $this->appId(),
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes()),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);

        return rtrim((string) config('services.tiktok.oauth_authorize_url', 'https://www.tiktok.com/v2/auth/authorize/'), '?').'?'.$query;
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        $configured = (string) config('services.tiktok.oauth_scopes', '');
        if ($configured !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        return self::REQUIRED_SCOPES;
    }

    public function appId(): string
    {
        return trim((string) config('services.tiktok.app_id', ''));
    }

    public function appSecret(): string
    {
        return trim((string) config('services.tiktok.app_secret', ''));
    }
}
