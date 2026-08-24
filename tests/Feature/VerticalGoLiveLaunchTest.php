<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Onboarding\VerticalGoLiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Flow;
use Modules\Reminders\Models\Source;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerticalGoLiveLaunchTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);
        $this->withoutMiddleware([
            EnsurePlanPlugin::class,
            \Modules\Wpbox\Http\Middleware\CheckPlan::class,
        ]);
    }

    public function test_unknown_pack_fails(): void
    {
        $result = app(VerticalGoLiveService::class)->install($this->company, 'not-a-pack');

        $this->assertFalse($result['success']);
    }

    public function test_healthcare_pack_seeds_catalog_bookings_and_flow_without_meta_credentials(): void
    {
        $result = app(VerticalGoLiveService::class)->install($this->company, 'healthcare');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertNotEmpty($result['flow_id']);
        $this->assertNotEmpty($result['catalog_id']);
        $this->assertCount(3, $result['booking_source_ids']);
        $this->assertSame('waiting_on_meta', $result['templates']['status']);
        $this->assertSame('healthcare', $this->company->getConfig('vertical_golive_pack'));

        $this->assertTrue(
            Flow::withoutGlobalScopes()->where('company_id', $this->company->id)->where('id', $result['flow_id'])->exists()
        );
        $this->assertTrue(
            ListCatalog::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->company->id)
                ->where('id', $result['catalog_id'])
                ->exists()
        );
        $this->assertSame(3, Source::queryForCompany($this->company->id)->count());

        $checklistKeys = collect($result['checklist'])->pluck('status', 'key');
        $this->assertSame('live', $checklistKeys['flow']);
        $this->assertSame('live', $checklistKeys['catalog']);
        $this->assertSame('live', $checklistKeys['bookings']);
        $this->assertSame('waiting_on_meta', $checklistKeys['templates']);
    }

    public function test_healthcare_pack_submits_booking_templates_when_waba_credentials_exist(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'tmpl_1',
                'status' => 'PENDING',
                'category' => 'UTILITY',
            ], 200),
        ]);

        $this->company->setConfig('whatsapp_permanent_access_token', 'test-token-value');
        $this->company->setConfig('whatsapp_business_account_id', 'waba-123');
        $this->company->setConfig('whatsapp_phone_number_id', 'phone-123');

        $result = app(VerticalGoLiveService::class)->install($this->company, 'healthcare');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertNotNull($result['templates']['booking']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_activation_install_stays_on_activation_and_shows_checklist(): void
    {
        $this->company->setConfig('whatsapp_webhook_verified', 'yes');
        $this->company->setConfig('whatsapp_settings_done', 'yes');

        $response = $this->actingAs($this->owner)
            ->post(route('activation.vertical'), [
                'vertical' => 'healthcare',
                'install_playbook' => 1,
            ]);

        $response->assertRedirect(route('activation.index'));
        $response->assertSessionHas('status');

        $follow = $this->actingAs($this->owner)->get(route('activation.index'));
        $follow->assertOk();
        $follow->assertSee('Go-live checklist');
        $follow->assertSee('Healthcare clinic');
    }
}
