<?php

namespace Modules\Social\Publishing;

use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

/**
 * Placeholder publisher used until real network adapters land.
 */
class NullSocialPublisher implements SocialPublisherInterface
{
    public function __construct(private readonly SocialProvider $provider)
    {
    }

    public function provider(): SocialProvider
    {
        return $this->provider;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        return PublishResult::fail(
            sprintf('%s publishing is not implemented yet.', $this->provider->label())
        );
    }

    public function refreshToken(SocialAccount $account): bool
    {
        return false;
    }
}
