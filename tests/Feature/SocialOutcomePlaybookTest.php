<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OutcomeAttribution;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Services\Outcomes\OutcomeMetricsService;
use App\Services\Outcomes\PlaybookInstaller;
use App\Services\Platform\InvoicePaidSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\JourneyStage;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialOutcomePlaybookTest extends TestCase
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
        config(['settings.enable_credits' => true]);
        $this->owner->addCredits(100, 'test');
    }

    public function test_paid_social_invoice_enrolls_lead_to_cash_without_whatsapp(): void
    {
        app(PlaybookInstaller::class)->install($this->company, 'lead_to_cash', installFlow: false);

        $post = SocialPost::factory()->create(['company_id' => $this->company->id]);
        $offer = SocialOfferLink::factory()->create([
            'company_id' => $this->company->id,
            'social_post_id' => $post->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'social_post_id' => $post->id,
            'social_offer_link_id' => $offer->id,
            'invoice_number' => 'INV-SOC-OUT-1',
            'customer_name' => 'Social Buyer',
            'customer_phone' => '+254712999001',
            'amount' => 2500,
            'currency' => 'KES',
            'status' => 'paid',
            'paid_at' => now(),
            'items' => [['title' => 'Offer item', 'quantity' => 1, 'total' => 2500]],
        ]);

        $this->assertDatabaseMissing('contacts', [
            'company_id' => $this->company->id,
            'phone' => '+254712999001',
        ]);

        app(InvoicePaidSyncService::class)->sync($invoice->fresh());

        $contact = Contact::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('phone', '+254712999001')
            ->first();

        $this->assertNotNull($contact);
        // Payment note is stored as an internal message; no WhatsApp campaign/outbound send.
        $this->assertSame(0, Message::query()->where('is_campign_messages', true)->count());
        $this->assertSame(0, Message::query()->where('is_note', false)->whereNotNull('fb_message_id')->count());
        $this->assertDatabaseHas('messages', [
            'contact_id' => $contact->id,
            'is_note' => true,
        ]);

        $journeyId = (int) $this->company->fresh()->getConfig('outcome_lead_to_cash_journey_id');
        $paidStage = JourneyStage::withoutGlobalScopes()
            ->where('journey_id', $journeyId)
            ->where('name', 'Paid')
            ->first();

        $this->assertNotNull($paidStage);
        $this->assertDatabaseHas('journey_stage_contacts', [
            'stage_id' => $paidStage->id,
            'contact_id' => $contact->id,
        ]);

        $attribution = OutcomeAttribution::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('sku', 'lead_to_cash')
            ->where('source', 'invoice')
            ->where('unique_key', 'like', '%'.$invoice->id)
            ->first();

        $this->assertNotNull($attribution);
        $this->assertSame('social', $attribution->metadata['source_channel'] ?? null);
        $this->assertSame($post->id, (int) ($attribution->metadata['social_post_id'] ?? 0));
        $this->assertSame($offer->id, (int) ($attribution->metadata['social_offer_link_id'] ?? 0));

        $this->assertDatabaseHas('platform_events', [
            'company_id' => $this->company->id,
            'event' => 'social.order.paid',
        ]);

        $socialEvent = PlatformEvent::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('event', 'social.order.paid')
            ->first();

        $this->assertSame($post->id, (int) ($socialEvent->payload['social_post_id'] ?? 0));
        $this->assertSame($invoice->id, (int) ($socialEvent->payload['invoice_id'] ?? 0));

        $metrics = app(OutcomeMetricsService::class)->forCompany($this->company);
        $this->assertSame(1, $metrics['lead_to_cash']['social_orders']);
        $this->assertEquals(2500.0, (float) $metrics['lead_to_cash']['social_revenue']);
    }

    public function test_non_social_paid_invoice_skips_social_event(): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Regular Buyer',
            'phone' => '+254712999002',
            'subscribed' => 1,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-SOC-OUT-2',
            'customer_name' => 'Regular Buyer',
            'customer_phone' => '+254712999002',
            'amount' => 800,
            'currency' => 'KES',
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => ['contact_id' => $contact->id],
        ]);

        app(InvoicePaidSyncService::class)->sync($invoice->fresh());

        $this->assertDatabaseMissing('platform_events', [
            'company_id' => $this->company->id,
            'event' => 'social.order.paid',
        ]);

        $metrics = app(OutcomeMetricsService::class)->forCompany($this->company);
        $this->assertSame(0, $metrics['lead_to_cash']['social_orders']);
    }

    public function test_lead_to_cash_checklist_mentions_social_offers(): void
    {
        $checklist = config('outcome-playbooks.playbooks.lead_to_cash.checklist');

        $this->assertTrue(
            collect($checklist)->contains(fn ($item) => str_contains(strtolower($item), 'social'))
        );
        $this->assertContains('social_orders', config('outcome-playbooks.playbooks.lead_to_cash.metrics'));
    }
}
