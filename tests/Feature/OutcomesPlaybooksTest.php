<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use App\Services\Outcomes\OutcomeJourneyEnroller;
use App\Services\Outcomes\OutcomeMetricsService;
use App\Services\Outcomes\PlaybookInstaller;
use App\Services\Outcomes\StoreCommerceWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Journies\Services\JourneyTemplateService;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutcomesPlaybooksTest extends TestCase
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

    public function test_journey_templates_include_outcome_playbooks(): void
    {
        $templates = app(JourneyTemplateService::class)->templates();

        $this->assertArrayHasKey('cart_recovery', $templates);
        $this->assertArrayHasKey('booking_convert', $templates);
        $this->assertArrayHasKey('lead_to_cash', $templates);
        $this->assertCount(5, $templates['cart_recovery']['stages']);
        $this->assertCount(6, $templates['lead_to_cash']['stages']);
    }

    public function test_playbook_installer_creates_journey_and_marks_installed(): void
    {
        $result = app(PlaybookInstaller::class)->install($this->company, 'lead_to_cash', installFlow: false);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['journey']);
        $this->assertSame('yes', $this->company->fresh()->getConfig('outcome_lead_to_cash_installed'));
        $this->assertDatabaseHas('journeys', [
            'id' => $result['journey']->id,
            'company_id' => $this->company->id,
        ]);
        $this->assertCount(6, $result['journey']->stages);
    }

    public function test_install_suite_installs_all_three_playbooks(): void
    {
        $result = app(PlaybookInstaller::class)->installSuite($this->company, installFlows: false);

        $this->assertTrue($result['success']);
        $this->assertSame('yes', $this->company->fresh()->getConfig('outcome_cart_recovery_installed'));
        $this->assertSame('yes', $this->company->fresh()->getConfig('outcome_booking_convert_installed'));
        $this->assertSame('yes', $this->company->fresh()->getConfig('outcome_lead_to_cash_installed'));
        $this->assertSame('yes', $this->company->fresh()->getConfig('outcome_commerce_ops_suite_installed'));
    }

    public function test_outcomes_page_renders_for_owner(): void
    {
        $response = $this->actingAs($this->owner)->get(route('outcomes.index'));

        $response->assertOk();
        $response->assertSee('Commerce Ops Outcomes');
        $response->assertSee('Cart Recovery');
        $response->assertSee('Booking Convert');
        $response->assertSee('Lead-to-Cash');
    }

    public function test_install_playbook_via_http(): void
    {
        $response = $this->actingAs($this->owner)->post(route('outcomes.install', 'cart_recovery'), [
            'install_flow' => 0,
        ]);

        $response->assertRedirect(route('outcomes.index'));
        $this->assertSame('yes', $this->company->fresh()->getConfig('outcome_cart_recovery_installed'));
    }

    public function test_cart_abandon_enrolls_contact_in_recovery_journey(): void
    {
        app(PlaybookInstaller::class)->install($this->company, 'cart_recovery', installFlow: false);

        $enrolled = app(OutcomeJourneyEnroller::class)->enrollCartAbandoned(
            $this->company,
            '+254700000001',
            'Cart User'
        );

        $this->assertTrue($enrolled);
        $this->assertDatabaseHas('contacts', [
            'company_id' => $this->company->id,
            'phone' => '+254700000001',
        ]);
    }

    public function test_customer360_includes_outcomes_payload(): void
    {
        app(PlaybookInstaller::class)->install($this->company, 'lead_to_cash', installFlow: false);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Outcome User',
            'phone' => '+254711000111',
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('customer360.show', $contact));

        $response->assertOk();
        $response->assertJsonStructure(['outcomes' => ['playbooks', 'open_abandoned_carts']]);
        $response->assertJsonPath('outcomes.playbooks.lead_to_cash.name', 'Lead-to-Cash');
    }

    public function test_outcome_metrics_service_returns_sku_keys(): void
    {
        $metrics = app(OutcomeMetricsService::class)->forCompany($this->company);

        $this->assertArrayHasKey('cart_recovery', $metrics);
        $this->assertArrayHasKey('booking_convert', $metrics);
        $this->assertArrayHasKey('lead_to_cash', $metrics);
    }

    public function test_shopify_checkout_webhook_enrolls_cart_recovery(): void
    {
        app(PlaybookInstaller::class)->install($this->company, 'cart_recovery', installFlow: false);
        $this->company->setConfig('plain_token', 'test-commerce-token');

        $request = Request::create('/webhooks/commerce/shopify/test-commerce-token', 'POST', [
            'id' => 999001,
            'email' => 'buyer@example.com',
            'phone' => '+254722000333',
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'One', 'phone' => '+254722000333'],
            'total_price' => '1500.00',
            'currency' => 'KES',
        ]);
        $request->headers->set('X-Shopify-Topic', 'checkouts/create');

        $result = app(StoreCommerceWebhookService::class)->handleShopify($this->company, $request);

        $this->assertTrue($result['handled']);
        $this->assertTrue($result['enrolled']);
        $this->assertDatabaseHas('contacts', [
            'company_id' => $this->company->id,
            'phone' => '+254722000333',
        ]);
    }

    public function test_commerce_webhook_http_endpoint(): void
    {
        $this->company->setConfig('plain_token', 'http-token-xyz');
        app(PlaybookInstaller::class)->install($this->company, 'cart_recovery', installFlow: false);

        $response = $this->postJson('/webhooks/commerce/shopify/http-token-xyz', [
            'id' => 42,
            'phone' => '+254733000444',
            'billing_address' => ['first_name' => 'A', 'last_name' => 'B', 'phone' => '+254733000444'],
        ], [
            'X-Shopify-Topic' => 'checkouts/update',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }
}
