<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogBookingPendingService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Flowmaker\Jobs\ResumeFlowFromListingInquiry;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogListingBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
    }

    public function test_generate_booking_stores_pending_without_resuming_flow(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->listingFlowContext();

        $contact->setContactState($flow->id, 'current_node', 'listing-1');

        $response = $this->postJson(route('catalog.generate-booking', $catalog->id), [
            'item_id' => 'home-1',
            'customerName' => 'Jane Doe',
            'customerPhone' => '254711111111',
            'preferredDateTime' => 'Saturday 10am',
            'notes' => 'Viewing',
            'flow_token' => $flowToken,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['message', 'whatsapp_url']);

        Queue::assertNotPushed(ResumeFlowFromListingInquiry::class);

        $contact->refresh();

        $this->assertSame('1', $contact->getContactStateValue($flow->id, CatalogBookingPendingService::PENDING_FLAG));
        $this->assertSame('listing-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('Diani Villa', $contact->getContactStateValue($flow->id, 'listing_booking_item_title'));
        $this->assertStringContainsString('Booking request from', $contact->getContactStateValue($flow->id, 'listing_booking_message'));
    }

    public function test_inbound_booking_message_advances_listing_node_once(): void
    {
        [$company, $catalog, $contact, $flow, $flowToken] = $this->listingFlowContext();

        $contact->setContactState($flow->id, 'current_node', 'listing-1');

        $this->postJson(route('catalog.generate-booking', $catalog->id), [
            'item_id' => 'home-1',
            'customerName' => 'Jane Doe',
            'customerPhone' => '254711111111',
            'preferredDateTime' => 'Saturday 10am',
            'flow_token' => $flowToken,
        ])->assertOk();

        $contact->refresh();
        $bookingText = $contact->getContactStateValue($flow->id, 'listing_booking_message');
        $this->assertNotSame('', $bookingText);

        $message = new \stdClass();
        $message->contact_id = $contact->id;
        $message->company_id = $company->id;
        $message->value = $bookingText;
        $message->extra = '';

        $flow->processMessage($message);

        $this->assertSame('quick-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'listing_inquiry_resumed'));
        $this->assertNotSame('1', $contact->getContactStateValue($flow->id, CatalogBookingPendingService::PENDING_FLAG));
    }

    public function test_generate_inquiry_with_flow_token_stores_pending_instead_of_dispatching_job(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->listingFlowContext('inquiry');

        $response = $this->postJson(route('catalog.generate-inquiry', $catalog->id), [
            'item_id' => 'home-1',
            'customerName' => 'Jane',
            'notes' => 'Interested',
            'flow_token' => $flowToken,
        ]);

        $response->assertOk();
        Queue::assertNotPushed(ResumeFlowFromListingInquiry::class);
        $this->assertSame('1', $contact->getContactStateValue($flow->id, CatalogBookingPendingService::PENDING_FLAG));
    }

    public function test_generate_booking_requires_phone(): void
    {
        [, $catalog] = $this->listingFlowContext();

        $response = $this->postJson(route('catalog.generate-booking', $catalog->id), [
            'item_id' => 'home-1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customerPhone']);
    }

    /**
     * @return array{0: Company, 1: ListCatalog, 2: Contact, 3: Flow, 4: string}
     */
    private function listingFlowContext(string $completionType = 'booking'): array
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_phone_number', '254712345678');

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Beach Homes',
            'slug' => 'beach-homes',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
            'version' => 1,
            'items' => [[
                'id' => 'home-1',
                'title' => 'Diani Villa',
                'description' => 'Sea view',
                'price' => 15000000,
                'metadata' => [
                    'location' => 'Diani',
                    'listing_status' => 'Available',
                ],
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254712345670',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Listing Flow',
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'listing-1',
                        'type' => 'listing_inquiry',
                        'data' => [
                            'settings' => [
                                'catalogId' => $catalog->id,
                                'completionType' => $completionType,
                                'bookingVariablePrefix' => 'listing_booking',
                                'bookingBackend' => 'whatsapp_only',
                            ],
                        ],
                    ],
                    [
                        'id' => 'quick-1',
                        'type' => 'quick_replies',
                        'data' => [
                            'settings' => [
                                'header' => 'Thanks',
                                'body' => 'We received your booking request.',
                                'activeButtons' => 1,
                                'button1' => 'Continue',
                            ],
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e-listing-quick',
                        'source' => 'listing-1',
                        'target' => 'quick-1',
                        'sourceHandle' => 'onListingInquiry',
                    ],
                ],
            ]),
        ]);

        $flowToken = app(CatalogFlowCallbackService::class)->makeToken(
            $flow->id,
            $contact->id,
            'listing-1',
            $catalog->id
        );

        return [$company, $catalog, $contact, $flow, $flowToken];
    }
}
