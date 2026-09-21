<?php

namespace Modules\Social\Services;

use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Publishing\SocialPublisherManager;

class SocialTokenRefreshService
{
    public function __construct(private readonly SocialPublisherManager $publishers)
    {
    }

    /**
     * Attempt token refresh for accounts that expire within the next day.
     *
     * @return array{refreshed: int, failed: int, skipped: int}
     */
    public function refreshDue(?int $companyId = null): array
    {
        $stats = ['refreshed' => 0, 'failed' => 0, 'skipped' => 0];

        $query = SocialAccount::withoutGlobalScopes()
            ->where('status', 'active')
            ->where(function ($builder) {
                $builder->whereNull('token_expires_at')
                    ->orWhere('token_expires_at', '<=', now()->addDay());
            });

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $query->orderBy('id')->chunkById(100, function ($accounts) use (&$stats) {
            foreach ($accounts as $account) {
                $provider = SocialProvider::tryFromString($account->provider);

                if ($provider === null) {
                    $stats['skipped']++;

                    continue;
                }

                // Facebook/Instagram Page tokens are long-lived and typically do not refresh via this path.
                if (in_array($provider, [SocialProvider::Facebook, SocialProvider::Instagram], true)
                    && ! $account->refresh_token) {
                    $stats['skipped']++;

                    continue;
                }

                $ok = $this->publishers->for($provider)->refreshToken($account);

                if ($ok) {
                    $stats['refreshed']++;
                } else {
                    $stats['failed']++;
                }
            }
        });

        return $stats;
    }
}
