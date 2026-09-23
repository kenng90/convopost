<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Models\SocialPostAnalyticsSnapshot;
use Modules\Social\Services\SocialAnalyticsSyncService;
use Tests\TestCase;

class SocialAnalyticsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_facebook_insights_into_snapshot(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    ['name' => 'post_impressions', 'values' => [['value' => 1200]]],
                    ['name' => 'post_impressions_unique', 'values' => [['value' => 900]]],
                    ['name' => 'post_engaged_users', 'values' => [['value' => 80]]],
                    ['name' => 'post_clicks', 'values' => [['value' => 25]]],
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
        ]);
        $account->setAccessToken('fb-token');
        $account->save();

        $post = SocialPost::factory()->published()->withDefaultVersion('Analytics post')->create([
            'company_id' => $company->id,
        ]);

        $pivot = SocialPostAccount::factory()->create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'provider_post_id' => '123_456',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $stats = app(SocialAnalyticsSyncService::class)->syncDue($company->id, 10);

        $this->assertSame(1, $stats['synced']);
        $this->assertDatabaseHas('social_post_analytics_snapshots', [
            'social_post_id' => $post->id,
            'social_post_account_id' => $pivot->id,
            'provider' => 'facebook',
            'impressions' => 1200,
            'reach' => 900,
            'engagement' => 80,
            'clicks' => 25,
        ]);

        $this->assertSame(1, SocialPostAnalyticsSnapshot::withoutGlobalScopes()->count());
    }
}
