<?php

namespace Modules\Tiktok\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TiktokTokenRefreshService
{
    public const REFRESH_LEAD_HOURS = 4;

    public function __construct(private readonly TiktokClient $client)
    {
    }

    /**
     * @return array{refreshed: int, failed: int, skipped: int}
     */
    public function refreshDue(?int $companyId = null): array
    {
        $stats = ['refreshed' => 0, 'failed' => 0, 'skipped' => 0];

        if (! $this->hasAppCredentials()) {
            Log::warning('messaging.tiktok.token_refresh.skipped_no_app_credentials');

            return $stats;
        }

        $query = ChannelConnection::withoutGlobalScopes()
            ->where('channel', MessagingChannelType::Tiktok->value)
            ->where('status', 'connected')
            ->orderBy('id');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $query->chunkById(50, function ($connections) use (&$stats) {
            foreach ($connections as $connection) {
                $outcome = $this->refreshConnectionIfDue($connection);
                $stats[$outcome]++;
            }
        });

        return $stats;
    }

    /**
     * @return 'refreshed'|'failed'|'skipped'
     */
    public function refreshConnectionIfDue(ChannelConnection $connection): string
    {
        $refreshToken = trim((string) $connection->credential('refresh_token', ''));
        if ($refreshToken === '') {
            return 'skipped';
        }

        if (! $this->isDue($connection)) {
            return 'skipped';
        }

        return $this->refreshConnection($connection) ? 'refreshed' : 'failed';
    }

    public function refreshConnection(ChannelConnection $connection): bool
    {
        $refreshToken = trim((string) $connection->credential('refresh_token', ''));
        if ($refreshToken === '' || ! $this->hasAppCredentials()) {
            return false;
        }

        $result = $this->client->refreshAccessToken($refreshToken);
        $accessToken = trim((string) data_get($result['data'], 'access_token', ''));

        if (! $result['ok'] || $accessToken === '') {
            Log::warning('messaging.tiktok.token_refresh.failed', [
                'connection_id' => $connection->id,
                'company_id' => $connection->company_id,
                'code' => $result['code'],
                'message' => $result['message'],
            ]);

            return false;
        }

        $credentials = $connection->credentials ?? [];
        $credentials['access_token'] = $accessToken;

        $newRefresh = trim((string) data_get($result['data'], 'refresh_token', ''));
        if ($newRefresh !== '') {
            $credentials['refresh_token'] = $newRefresh;
        }

        $expiresIn = (int) data_get($result['data'], 'expires_in', data_get($result['data'], 'expires', 0));
        $credentials['access_token_expires_at'] = $expiresIn > 0
            ? now()->addSeconds($expiresIn)->toIso8601String()
            : now()->addHours(23)->toIso8601String();

        $refreshExpiresIn = (int) data_get($result['data'], 'refresh_token_expires_in', 0);
        if ($refreshExpiresIn > 0) {
            $credentials['refresh_token_expires_at'] = now()->addSeconds($refreshExpiresIn)->toIso8601String();
        }

        $connection->update(['credentials' => $credentials]);

        $company = Company::withoutGlobalScopes()->find($connection->company_id);
        $company?->setConfig('tiktok_access_token', $accessToken);

        return true;
    }

    private function isDue(ChannelConnection $connection): bool
    {
        $expiresAt = $connection->credential('access_token_expires_at');
        if ($expiresAt === null || $expiresAt === '') {
            return true;
        }

        try {
            return Carbon::parse((string) $expiresAt)
                ->subHours(self::REFRESH_LEAD_HOURS)
                ->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    private function hasAppCredentials(): bool
    {
        return (string) config('services.tiktok.app_id', '') !== ''
            && (string) config('services.tiktok.app_secret', '') !== '';
    }
}
