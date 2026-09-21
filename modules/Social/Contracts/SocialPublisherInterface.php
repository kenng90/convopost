<?php

namespace Modules\Social\Contracts;

use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

interface SocialPublisherInterface
{
    public function provider(): SocialProvider;

    /**
     * Publish a post version to the given connected account.
     *
     * @param  list<string>  $mediaUrls
     */
    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult;

    /**
     * Refresh the account access token when supported.
     */
    public function refreshToken(SocialAccount $account): bool;
}
