<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsappSetupGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->withoutMiddleware([
            EnsurePlanPlugin::class,
            \App\Http\Middleware\EnsureOwnerIsOnPROPlan::class,
            \Modules\Wpbox\Http\Middleware\CheckPlan::class,
        ]);
    }

    public function test_embedded_setup_page_shows_prerequisites_and_steps(): void
    {
        config(['embeddedlogin.config_id' => 'test-config-id']);

        $response = $this->actingAs($this->owner)->get(route('whatsapp.setup'));

        $response->assertOk();
        $response->assertSee(__('Before you start'));
        $response->assertSee(__('How to connect'));
        $response->assertSee(__('Click “WhatsApp Setup” below'));
        $response->assertSee(__('Checklist'));
        $response->assertSee(__('WhatsApp Setup completed'));
        $response->assertSee(__('Start with WhatsApp Setup on the left, finish every Meta screen, then refresh this status.'));
    }

    public function test_connected_embedded_setup_hides_prerequisites(): void
    {
        config(['embeddedlogin.config_id' => 'test-config-id']);

        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
            'whatsapp_permanent_access_token' => 'token',
            'whatsapp_phone_number_id' => '123',
            'whatsapp_business_account_id' => '456',
        ]);

        $response = $this->actingAs($this->owner)->get(route('whatsapp.setup'));

        $response->assertOk();
        $response->assertDontSee(__('Before you start'));
        $response->assertSee(__('Connected'));
        $response->assertSee(__('Success!'));
    }

    public function test_manual_setup_status_shows_step_checklist(): void
    {
        config(['embeddedlogin.config_id' => '']);

        $response = $this->actingAs($this->owner)->get(route('whatsapp.setup'));

        $response->assertOk();
        $response->assertSee(__('Checklist'));
        $response->assertSee(__('Webhook verified (Step 1)'));
        $response->assertSee(__('Permanent access token (Step 2)'));
        $response->assertSee(__('Phone number ID & Business Account ID (Step 3)'));
        $response->assertSee(__('Complete Steps 1–3 on the left in order, then refresh this status.'));
    }
}
