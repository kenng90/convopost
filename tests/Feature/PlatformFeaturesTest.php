<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use App\Services\Flowmaker\AiFlowAssistantService;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\Platform\ActivationService;
use App\Services\Platform\HealthMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Services\JourneyTemplateService;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformFeaturesTest extends TestCase
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

        $this->withoutMiddleware(EnsurePlanPlugin::class);
    }

    public function test_activation_service_tracks_whatsapp_step(): void
    {
        $service = app(ActivationService::class);

        $this->assertFalse($service->isWhatsappConnected($this->company));

        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $this->assertTrue($service->isWhatsappConnected($this->company));
        $this->assertGreaterThan(0, $service->progressPercent($this->company->fresh()));
    }

    public function test_activation_wizard_requires_whatsapp_first(): void
    {
        $response = $this->actingAs($this->owner)->get(route('activation.index'));

        $response->assertRedirect(route('whatsapp.setup'));
    }

    public function test_activation_wizard_shows_when_whatsapp_connected(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $response = $this->actingAs($this->owner)->get(route('activation.index'));

        $response->assertOk();
        $response->assertSee('Get to your first reply');
    }

    public function test_flow_template_install_creates_flow(): void
    {
        $flow = app(FlowTemplateService::class)->install('lead_capture');

        $this->assertNotNull($flow);
        $this->assertDatabaseHas('flows', ['id' => $flow->id, 'name' => 'Lead Capture']);
        $this->assertStringContainsString('keyword_trigger', $flow->flow_data);
    }

    public function test_flows_index_shows_template_cards(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $response = $this->actingAs($this->owner)->get(route('flows.index'));

        $response->assertOk();
        $response->assertSee('Lead Capture');
        $response->assertSee('Use template');
        $response->assertSee('AI Flow Assistant');
        $response->assertSee('Generate draft');
    }

    public function test_flow_create_from_template_redirects_to_editor(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $response = $this->actingAs($this->owner)->get(route('flows.create-from-template', 'lead_capture'));

        $response->assertRedirect();
        $this->assertDatabaseHas('flows', ['name' => 'Lead Capture', 'company_id' => $this->company->id]);
    }

    public function test_ai_flow_assistant_generates_valid_graph(): void
    {
        $draft = app(AiFlowAssistantService::class)->generate('When customer says pay, collect M-Pesa payment');

        $this->assertNotEmpty($draft['nodes']);
        $this->assertNotEmpty($draft['edges']);
        $this->assertTrue(collect($draft['nodes'])->contains(fn ($n) => $n['type'] === 'mpesa_stk_push'));
    }

    public function test_public_invoice_page_renders(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'TST-001',
            'customer_name' => 'Jane',
            'customer_phone' => '+254700000099',
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [['title' => 'Service', 'quantity' => 1, 'total' => 500]],
        ]);

        $response = $this->get(route('invoice.public.show', $invoice->public_uuid));

        $response->assertOk();
        $response->assertSee('TST-001');
        $response->assertSee('Jane');
    }

    public function test_health_monitor_returns_alerts_when_webhook_missing(): void
    {
        $alerts = app(HealthMonitorService::class)->alerts($this->company);

        $this->assertNotEmpty($alerts);
        $this->assertTrue(collect($alerts)->contains(fn ($a) => $a['code'] === 'webhook_unverified'));
    }

    public function test_journey_revenue_playbook_creates_five_stages(): void
    {
        $journey = app(JourneyTemplateService::class)->createFromTemplate('revenue');

        $this->assertNotNull($journey);
        $this->assertCount(5, $journey->stages);
        $this->assertSame('Onboarded', $journey->stages->last()->name);
    }

    public function test_integration_hub_connects_provider(): void
    {
        $response = $this->actingAs($this->owner)->post(route('integrations.connect', 'zapier'), [
            'webhook_url' => 'https://hooks.zapier.com/example',
        ]);

        $response->assertRedirect(route('integrations.index'));
        $this->assertDatabaseHas('integration_connectors', [
            'company_id' => $this->company->id,
            'provider' => 'zapier',
            'status' => 'connected',
        ]);
    }

    public function test_customer360_api_returns_contact_payload(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Test User',
            'phone' => '+254711111111',
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('customer360.show', $contact));

        $response->assertOk();
        $response->assertJsonPath('contact.name', 'Test User');
        $response->assertJsonStructure(['conversation_summary', 'journeys', 'orders', 'campaigns']);
    }

    public function test_copilot_suggest_returns_suggestions(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Copilot User',
            'phone' => '+254722222222',
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/wpbox/copilot/'.$contact->id.'/suggest');

        $response->assertOk();
        $response->assertJsonStructure(['suggestions', 'tone_variants']);
    }
}
