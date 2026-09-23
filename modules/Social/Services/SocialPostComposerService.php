<?php

namespace Modules\Social\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialLabel;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Models\SocialPostVersion;

class SocialPostComposerService
{
    /**
     * @param  array{
     *     content: string,
     *     account_ids: list<int>,
     *     media_ids?: list<int>,
     *     label_ids?: list<int>,
     *     versions?: array<string, string>,
     *     first_comment?: string|null,
     *     status?: string,
     *     scheduled_at?: string|null,
     *     offer_type?: string|null,
     *     offer_url?: string|null,
     *     offer_target_id?: int|null
     * }  $data
     */
    public function create(Company $company, User $user, array $data): SocialPost
    {
        return DB::transaction(function () use ($company, $user, $data) {
            $status = $data['status'] ?? 'draft';
            $scheduledAt = $data['scheduled_at'] ?? null;
            $firstComment = isset($data['first_comment']) ? trim((string) $data['first_comment']) : '';
            $firstComment = $firstComment !== '' ? $firstComment : null;

            if ($status === 'scheduled' && empty($scheduledAt)) {
                $status = 'draft';
            }

            $post = SocialPost::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'status' => $status,
                'approval_status' => 'none',
                'scheduled_at' => $status === 'scheduled' ? $scheduledAt : null,
                'published_at' => null,
                'label_ids' => $this->ownedLabelIds($company->id, $data['label_ids'] ?? []),
            ]);

            $mediaIds = $this->ownedMediaIds($company->id, $data['media_ids'] ?? []);

            SocialPostVersion::query()->create([
                'social_post_id' => $post->id,
                'provider' => 'default',
                'content' => $data['content'],
                'media_ids' => $mediaIds,
                'first_comment' => $firstComment,
                'provider_payload' => [],
            ]);

            foreach (($data['versions'] ?? []) as $provider => $content) {
                $content = trim((string) $content);
                if ($content === '' || $provider === 'default') {
                    continue;
                }

                SocialPostVersion::query()->create([
                    'social_post_id' => $post->id,
                    'provider' => $provider,
                    'content' => $content,
                    'media_ids' => $mediaIds,
                    'first_comment' => $firstComment,
                    'provider_payload' => [],
                ]);
            }

            $accountIds = $this->ownedAccountIds($company->id, $data['account_ids'] ?? []);

            foreach ($accountIds as $accountId) {
                SocialPostAccount::query()->create([
                    'social_post_id' => $post->id,
                    'social_account_id' => $accountId,
                    'status' => 'pending',
                ]);
            }

            $this->syncOffer($post, $company, $data);

            app(SocialPostApprovalService::class)->record(
                $post,
                'created',
                $user,
                $status === 'scheduled' ? __('Post scheduled') : __('Draft created')
            );

            return $post->fresh(['defaultVersion', 'accounts', 'offerLink']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncOffer(SocialPost $post, Company $company, array $data): void
    {
        $offerType = $data['offer_type'] ?? null;

        if (! $offerType || $offerType === 'none') {
            return;
        }

        $url = null;
        $targetId = null;

        if ($offerType === 'url') {
            $url = $data['offer_url'] ?? null;
            if (! $url) {
                return;
            }
        }

        if (in_array($offerType, ['product', 'catalog'], true)) {
            $targetId = isset($data['offer_target_id']) ? (int) $data['offer_target_id'] : null;
            if (! $targetId) {
                return;
            }
            $url = $data['offer_url'] ?? null;
        }

        SocialOfferLink::query()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'offer_type' => $offerType,
            'target_id' => $targetId,
            'url' => $url,
            'tracking_token' => Str::random(40),
            'click_count' => 0,
        ]);
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    protected function ownedAccountIds(int $companyId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        return SocialAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    protected function ownedLabelIds(int $companyId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        return SocialLabel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    protected function ownedMediaIds(int $companyId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        return SocialMediaAsset::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
