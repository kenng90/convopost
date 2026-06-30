<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Catalog\CatalogBookingVariableService;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Tests\TestCase;

class CatalogBookingVariableServiceTest extends TestCase
{
    public function test_store_on_contact_writes_prefixed_booking_variables(): void
    {
        $company = Company::factory()->create();

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254700000001',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Listing Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $item = [
            'id' => 'home-1',
            'title' => 'Diani Villa',
            'price' => 1000000,
        ];

        $service = app(CatalogBookingVariableService::class);
        $service->storeOnContact(
            $contact,
            $flow->id,
            'listing_booking',
            $item,
            [
                'customerName' => 'Jane Doe',
                'customerPhone' => '254711111111',
                'preferredDateTime' => 'Saturday 10am',
                'notes' => 'Viewing request',
                'completionType' => 'booking',
            ],
            'Booking message text'
        );

        $this->assertSame('Diani Villa', $contact->getContactStateValue($flow->id, 'listing_booking_item_title'));
        $this->assertSame('Jane Doe', $contact->getContactStateValue($flow->id, 'listing_booking_customer_name'));
        $this->assertSame('254711111111', $contact->getContactStateValue($flow->id, 'listing_booking_customer_phone'));
        $this->assertSame('Saturday 10am', $contact->getContactStateValue($flow->id, 'listing_booking_preferred_datetime'));
        $this->assertSame('Booking message text', $contact->getContactStateValue($flow->id, 'listing_booking_message'));
        $this->assertNotNull($contact->getContactStateValue($flow->id, 'selected_listing'));
    }
}
