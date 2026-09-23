<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAnalyticsSnapshot;
use Modules\Social\Services\SocialInsightsService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    public function test_insights_page_shows_reach_and_attributed_revenue(): void
    {
        [$owner, $company] = $this->ownerWithAnalytics();

        $post = SocialPost::factory()->published()->withDefaultVersion('Launch offer post')->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
        ]);

        SocialPostAnalyticsSnapshot::factory()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'provider' => 'facebook',
            'impressions' => 1000,
            'reach' => 800,
            'engagement' => 120,
            'clicks' => 40,
            'synced_at' => now(),
        ]);

        Invoice::create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'invoice_number' => 'INV-SOCIAL-1',
            'customer_name' => 'Customer',
            'customer_phone' => '254700000000',
            'amount' => 2500,
            'currency' => 'KES',
            'status' => 'paid',
        ]);

        $payload = app(SocialInsightsService::class)->forCompany($company);

        $this->assertSame(800, $payload['totals']['reach']);
        $this->assertSame(120, $payload['totals']['engagement']);
        $this->assertSame(1, $payload['totals']['orders']);
        $this->assertEquals(2500.0, $payload['totals']['revenue']);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.insights'))
            ->assertOk()
            ->assertSee('Launch offer post')
            ->assertSee('800')
            ->assertSee('2,500.00');
    }

    public function test_insights_requires_social_analytics_capability(): void
    {
        $plan = Plans::create([
            'name' => 'No Analytics '.uniqid(),
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);
        $plan->setConfig('capabilities', json_encode(['social_publish']));
        $plan->setConfig('plugins', json_encode(['social']));

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.insights'))
            ->assertRedirect(route('plans.current'));
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function ownerWithAnalytics(): array
    {
        $plan = Plans::create([
            'name' => 'Analytics '.uniqid(),
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);
        $plan->setConfig('capabilities', json_encode(['social_publish', 'social_analytics']));
        $plan->setConfig('plugins', json_encode(['social']));

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
    }
}
