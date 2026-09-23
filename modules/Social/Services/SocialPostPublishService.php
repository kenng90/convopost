<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Log;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\SocialPublisherManager;
use Modules\Social\Support\PublishResult;
use Throwable;

class SocialPostPublishService
{
    public function __construct(private readonly SocialPublisherManager $publishers)
    {
    }

    public function publish(SocialPost $post): SocialPost
    {
        $post->loadMissing(['postAccounts.account', 'versions', 'defaultVersion']);

        if (! in_array($post->status, ['scheduled', 'failed'], true)) {
            return $post;
        }

        if (app(SocialPostApprovalService::class)->requiresApprovalBeforePublish($post)) {
            Log::info('Skipping social publish; post awaits approval', [
                'social_post_id' => $post->id,
                'approval_status' => $post->approval_status,
            ]);

            return $post;
        }

        $post->forceFill(['status' => 'publishing'])->save();

        $pending = $post->postAccounts->filter(
            fn (SocialPostAccount $pivot) => in_array($pivot->status, ['pending', 'failed'], true)
        );

        foreach ($pending as $pivot) {
            $this->publishToAccount($post, $pivot);
        }

        return $this->finalizePostStatus($post->fresh(['postAccounts']));
    }

    protected function publishToAccount(SocialPost $post, SocialPostAccount $pivot): void
    {
        $account = $pivot->account;

        if (! $account) {
            $pivot->markFailed('Connected account is missing.');

            return;
        }

        $provider = SocialProvider::tryFromString($account->provider);

        if ($provider === null) {
            $pivot->markFailed('Unknown social provider: '.$account->provider);

            return;
        }

        $version = $this->versionFor($post, $provider);
        $mediaUrls = $this->mediaUrls($post->company_id, $version->media_ids ?? []);

        try {
            $result = $this->publishers->for($provider)->publish($account, $version, $mediaUrls);
        } catch (Throwable $e) {
            report($e);
            $pivot->markFailed($e->getMessage());

            return;
        }

        if ($result->success && ! empty($result->meta['first_comment_error'])) {
            Log::warning('Social first comment failed after publish', [
                'social_post_id' => $post->id,
                'social_account_id' => $account->id,
                'provider_post_id' => $result->providerPostId,
                'error' => $result->meta['first_comment_error'],
            ]);
        }

        $this->applyResult($pivot, $result);
    }

    protected function applyResult(SocialPostAccount $pivot, PublishResult $result): void
    {
        if ($result->success) {
            $pivot->markPublished($result->providerPostId);

            return;
        }

        $pivot->markFailed($result->error ?: 'Publish failed.');
    }

    protected function versionFor(SocialPost $post, SocialProvider $provider): SocialPostVersion
    {
        $override = $post->versions->firstWhere('provider', $provider->value);
        $default = $post->versions->firstWhere('provider', 'default') ?? $post->defaultVersion;

        $version = $override ?? $default;

        if (! $version) {
            return new SocialPostVersion([
                'social_post_id' => $post->id,
                'provider' => 'default',
                'content' => '',
                'media_ids' => [],
                'first_comment' => null,
            ]);
        }

        // Network overrides inherit the default first comment when unset.
        if ($override && blank($override->first_comment) && $default && filled($default->first_comment)) {
            $override->setAttribute('first_comment', $default->first_comment);
        }

        // X overrides inherit thread replies from the default version when unset.
        if (
            $override
            && $provider === SocialProvider::X
            && blank(data_get($override->provider_payload, 'thread'))
            && $default
            && filled(data_get($default->provider_payload, 'thread'))
        ) {
            $payload = is_array($override->provider_payload) ? $override->provider_payload : [];
            $payload['thread'] = data_get($default->provider_payload, 'thread');
            $override->setAttribute('provider_payload', $payload);
        }

        return $version;
    }

    /**
     * @param  list<int|string>|null  $mediaIds
     * @return list<string>
     */
    protected function mediaUrls(int $companyId, ?array $mediaIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $mediaIds ?? [])));

        if ($ids === []) {
            return [];
        }

        return SocialMediaAsset::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->get()
            ->map(function (SocialMediaAsset $asset) {
                $url = $asset->url();

                if (! $url) {
                    return null;
                }

                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }

                return url($url);
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function finalizePostStatus(SocialPost $post): SocialPost
    {
        $pivots = $post->postAccounts;
        $total = $pivots->count();

        if ($total === 0) {
            $post->forceFill([
                'status' => 'failed',
            ])->save();

            Log::warning('Social post had no accounts to publish.', ['social_post_id' => $post->id]);

            return $post->fresh();
        }

        $published = $pivots->where('status', 'published')->count();
        $failed = $pivots->where('status', 'failed')->count();

        if ($published === $total) {
            $post->forceFill([
                'status' => 'published',
                'published_at' => now(),
            ])->save();
        } elseif ($published > 0) {
            $post->forceFill([
                'status' => 'published',
                'published_at' => $post->published_at ?? now(),
            ])->save();
        } elseif ($failed === $total) {
            $post->forceFill([
                'status' => 'failed',
            ])->save();
        } else {
            $post->forceFill([
                'status' => 'scheduled',
            ])->save();
        }

        return $post->fresh(['postAccounts']);
    }
}
