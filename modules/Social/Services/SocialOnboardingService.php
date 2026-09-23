<?php

namespace Modules\Social\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Route;
use Modules\Invoice\Models\Invoice;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;

class SocialOnboardingService
{
    /**
     * @return array{
     *     complete: bool,
     *     completed_count: int,
     *     total: int,
     *     steps: list<array{key: string, title: string, detail: string, done: bool, route: ?string, cta: ?string}>
     * }
     */
    public function forCompany(?Company $company): array
    {
        if (! $company) {
            return [
                'complete' => false,
                'completed_count' => 0,
                'total' => 3,
                'steps' => [],
            ];
        }

        $hasAccount = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->exists();

        $hasPublishedPost = SocialPost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'published')
            ->exists();

        $hasAttributedOrder = Invoice::query()
            ->where('company_id', $company->id)
            ->whereNotNull('social_post_id')
            ->where('status', 'paid')
            ->exists();

        $steps = [
            [
                'key' => 'connect_account',
                'title' => __('Connect a social account'),
                'detail' => __('Link Facebook, Instagram, or another network to publish from :brand.', [
                    'brand' => \Modules\Social\Support\SocialBrand::platformName($company),
                ]),
                'done' => $hasAccount,
                'route' => Route::has('social.accounts.index') ? 'social.accounts.index' : null,
                'cta' => __('Connect accounts'),
            ],
            [
                'key' => 'first_post',
                'title' => __('Publish your first post'),
                'detail' => __('Compose and publish (or schedule and let it go live) to start the content → commerce loop.'),
                'done' => $hasPublishedPost,
                'route' => Route::has('social.posts.create') ? 'social.posts.create' : null,
                'cta' => __('Compose post'),
            ],
            [
                'key' => 'first_attributed_order',
                'title' => __('Get your first attributed order'),
                'detail' => __('Attach a tracked offer to a post; paid checkouts with that attribution complete this step.'),
                'done' => $hasAttributedOrder,
                'route' => Route::has('social.insights') ? 'social.insights' : null,
                'cta' => __('View insights'),
            ],
        ];

        $completed = collect($steps)->where('done', true)->count();

        return [
            'complete' => $completed === count($steps),
            'completed_count' => $completed,
            'total' => count($steps),
            'steps' => $steps,
        ];
    }
}
