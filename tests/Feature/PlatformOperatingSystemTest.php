<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use App\Services\Agents\ActionAgentService;
use App\Services\Agents\AgentHandoffService;
use App\Services\Identity\CustomerGraphService;
use App\Services\Integrations\PlatformEventBus;
use App\Services\Onboarding\VerticalGoLiveService;
use App\Services\Outcomes\StoreCommerceWebhookService;
use App\Services\Trust\ConsentService;
use App\Services\Workspace\ConversationSlaService;
use App\Services\Workspace\CsatService;
use App\Services\Workspace\InboxRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformOperatingSystemTest extends TestCase
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

    public function test_action_agent_handoffs_on_human_request(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Ada',
            'phone' => '+254700000001',
            'enabled_ai_bot' => true,
        ]);

        $result = app(ActionAgentService::class)->ruleBased($this->company, $contact, 'I want to talk to a human');

        $this->assertTrue($result['handoff']);
        $this->assertContains('handoff_to_human', $result['tools_used']);
        $this->assertFalse((bool) $contact->fresh()->enabled_ai_bot);
    }

    public function test_assigning_agent_disables_bot(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Ken',
            'phone' => '+254700000002',
            'enabled_ai_bot' => true,
        ]);

        $staff = User::factory()->create(['company_id' => $this->company->id]);
        $staff->assignRole('staff');

        $this->actingAs($this->owner)->post('/api/wpbox/assign/'.$contact->id, [
            'user_id' => $staff->id,
        ])->assertOk();

        $this->assertSame($staff->id, (int) $contact->fresh()->user_id);
        $this->assertFalse((bool) $contact->fresh()->enabled_ai_bot);
    }

    public function test_inbox_routing_round_robin_rotates(): void
    {
        $a = User::factory()->create(['company_id' => $this->company->id]);
        $b = User::factory()->create(['company_id' => $this->company->id]);
        $a->assignRole('staff');
        $b->assignRole('staff');

        $routing = app(InboxRoutingService::class);
        $first = $routing->nextAgentId($this->company);
        $second = $routing->nextAgentId($this->company);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second);
    }

    public function test_sla_starts_and_csat_records_score(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Sla',
            'phone' => '+254700000003',
        ]);

        $workspace = app(ConversationSlaService::class)->start($this->company, $contact);
        $this->assertNotNull($workspace->sla_due_at);

        $csat = app(CsatService::class)->submit($this->company, $contact, 5, 'Great');
        $this->assertSame(5, $csat->csat_score);
    }

    public function test_customer_graph_finds_by_phone(): void
    {
        $existing = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Graph',
            'phone' => '+254700000004',
        ]);

        $found = app(CustomerGraphService::class)->findOrCreate($this->company, [
            'phone' => '+254700000004',
            'name' => 'Graph',
        ]);

        $this->assertSame($existing->id, $found->id);
    }

    public function test_event_bus_records_signed_event(): void
    {
        Http::fake();

        $event = app(PlatformEventBus::class)->emit($this->company, 'invoice.paid', [
            'phone' => '+254700000005',
            'amount' => 100,
        ]);

        $this->assertNotEmpty($event->signature);
        $this->assertSame('invoice.paid', $event->event);
        $this->assertDatabaseHas('platform_events', ['id' => $event->id, 'company_id' => $this->company->id]);
    }

    public function test_consent_records_opt_in(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Consent',
            'phone' => '+254700000006',
        ]);

        app(ConsentService::class)->record($this->company, $contact, 'opt_in', 'whatsapp', 'test');

        $this->assertTrue(app(ConsentService::class)->hasOptIn($this->company, $contact));
    }

    public function test_woocommerce_abandoned_cart_topic_is_handled(): void
    {
        $request = Request::create('/webhooks/commerce/woocommerce/token', 'POST', [
            'phone' => '+254700000007',
            'billing' => ['first_name' => 'Woo', 'last_name' => 'Cart'],
            'total' => '1200',
        ]);
        $request->headers->set('X-WC-Webhook-Topic', 'checkout.created');

        $result = app(StoreCommerceWebhookService::class)->handleWooCommerce($this->company, $request);

        $this->assertTrue($result['handled']);
        $this->assertSame('cart.abandoned', $result['event']);
    }

    public function test_vertical_go_live_unknown_pack_fails(): void
    {
        $result = app(VerticalGoLiveService::class)->install($this->company, 'not-a-pack');

        $this->assertFalse($result['success']);
    }

    public function test_handoff_service_assigns_and_disables_bot(): void
    {
        $staff = User::factory()->create(['company_id' => $this->company->id]);
        $staff->assignRole('staff');
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Handoff',
            'phone' => '+254700000008',
            'enabled_ai_bot' => true,
        ]);

        $result = app(AgentHandoffService::class)->handoff($this->company, $contact, 'Need a person', $staff->id);

        $this->assertTrue($result['ok']);
        $this->assertSame($staff->id, $result['assigned_to']);
        $this->assertFalse((bool) $contact->fresh()->enabled_ai_bot);
    }

    public function test_customer360_includes_identities_and_workspace(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => '360',
            'phone' => '+254700000009',
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('customer360.show', $contact));

        $response->assertOk();
        $response->assertJsonStructure(['identities', 'workspace', 'conversation_summary']);
    }

    public function test_trust_page_renders(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $this->actingAs($this->owner)->get(route('trust.index'))->assertOk();
    }

    public function test_agency_page_renders(): void
    {
        $this->company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);

        $this->actingAs($this->owner)->get(route('agency.index'))->assertOk()->assertSee('Agency portfolio');
    }
}
