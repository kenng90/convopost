<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Credit;
use App\Models\Plans;
use App\Models\User;
use App\Services\Platform\ManagedAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCreditGrantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Company $company;

    private Plans $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'owner']);

        config([
            'settings.enable_credits' => true,
            'managed-ai.enabled' => true,
            'managed-ai.platform_openrouter_api_key' => 'sk-platform-test',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->plan = Plans::create([
            'name' => 'Pro',
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
        ]);
        $this->plan->setConfig('managed_ai_monthly_credits', '100');

        $this->owner = User::factory()->create(['plan_id' => $this->plan->id]);
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'name' => 'Spa Co',
        ]);
    }

    public function test_admin_can_view_grant_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('credits.create', ['company_id' => $this->company->id]));

        $response->assertOk();
        $response->assertSee('Grant credits');
        $response->assertSee('Spa Co');
    }

    public function test_non_admin_cannot_grant_credits(): void
    {
        $response = $this->actingAs($this->owner)->post(route('credits.store'), [
            'company_id' => $this->company->id,
            'messaging_credits' => 50,
            'ai_credits' => 0,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, $this->owner->fresh()->getTotalRemainingCredits());
    }

    public function test_admin_can_grant_messaging_and_ai_credits(): void
    {
        $response = $this->actingAs($this->admin)->post(route('credits.store'), [
            'company_id' => $this->company->id,
            'messaging_credits' => 250,
            'ai_credits' => 40,
            'messaging_expires_at' => now()->addDays(20)->toDateString(),
            'note' => 'Mid-month top-up',
        ]);

        $response->assertRedirect(route('credits.create', ['company_id' => $this->company->id]));
        $response->assertSessionHas('status');

        $this->assertEquals(250, $this->owner->fresh()->getTotalRemainingCredits());

        $credit = Credit::query()->where('user_id', $this->owner->id)->first();
        $this->assertNotNull($credit);
        $this->assertStringStartsWith('admin:manual:'.$this->admin->id, (string) $credit->source);

        $ai = app(ManagedAiService::class)->status($this->company->fresh());
        $this->assertSame(140, $ai['monthly_allowance']);
        $this->assertSame(40, $ai['bonus_credits']);
        $this->assertSame(140, $ai['remaining']);
    }

    public function test_grant_requires_at_least_one_credit_amount(): void
    {
        $response = $this->actingAs($this->admin)->from(route('credits.create', [
            'company_id' => $this->company->id,
        ]))->post(route('credits.store'), [
            'company_id' => $this->company->id,
            'messaging_credits' => 0,
            'ai_credits' => 0,
        ]);

        $response->assertSessionHasErrors('messaging_credits');
    }

    public function test_ai_bonus_clears_on_new_billing_period(): void
    {
        $service = app(ManagedAiService::class);
        $service->grantBonusCredits($this->owner, 25);
        $this->owner->setConfig('managed_ai_period', '2020-01');

        $status = $service->status($this->company->fresh());

        $this->assertSame(0, $status['bonus_credits']);
        $this->assertSame(100, $status['monthly_allowance']);
        $this->assertSame('0', $this->owner->fresh()->getConfig('managed_ai_bonus_credits', '0'));
    }
}
