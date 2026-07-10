<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Flowmaker\Jobs\ResumeFlowFromListingInquiry;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogListingSlotBookingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Source $source;

    private AppointmentStaff $appointmentStaff;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'slot-catalog',
        ]);
        $owner->update(['company_id' => $this->company->id]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $staffUser->assignRole('staff');

        $this->appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'whatsapp_phone' => '+254712345670',
            'is_active' => true,
        ]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'AC Service & Gas Refill',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30, 60],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']])
                ->all(),
        ]);

        SourceStaff::create([
            'source_id' => $this->source->id,
            'appointment_staff_id' => $this->appointmentStaff->id,
            'is_active' => true,
        ]);
    }

    public function test_booking_config_returns_slots_mode_when_source_is_linked(): void
    {
        $catalog = $this->makeServiceCatalog();

        $response = $this->getJson(route('catalog.item.booking-config', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-1',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('mode', 'slots');
        $response->assertJsonPath('source.name', 'AC Service & Gas Refill');
    }

    public function test_booking_config_returns_whatsapp_mode_without_linked_source(): void
    {
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id,
            'name' => 'Unlinked Services',
            'slug' => 'unlinked-services',
            'catalog_mode' => CatalogMode::SERVICE,
            'vertical' => 'general_service',
            'version' => 1,
            'items' => [[
                'id' => 'service-2',
                'title' => 'Mystery Service',
                'price' => 2500,
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->getJson(route('catalog.item.booking-config', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-2',
        ]));

        $response->assertOk();
        $response->assertJsonPath('mode', 'whatsapp');
    }

    public function test_availability_endpoints_return_dates_and_slots(): void
    {
        $catalog = $this->makeServiceCatalog();
        $date = now('UTC')->addDay()->toDateString();

        $datesResponse = $this->getJson(route('catalog.item.availability.dates', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-1',
        ]));

        $datesResponse->assertOk();
        $datesResponse->assertJsonPath('success', true);
        $this->assertNotEmpty($datesResponse->json('dates'));

        $slotsResponse = $this->getJson(route('catalog.item.availability.slots', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-1',
        ]).'?'.http_build_query([
            'date' => $date,
            'duration_minutes' => 30,
        ]));

        $slotsResponse->assertOk();
        $slotsResponse->assertJsonPath('success', true);
        $slotsResponse->assertJsonStructure(['slots']);
        $this->assertNotEmpty($slotsResponse->json('slots'));
    }

    public function test_book_item_creates_reservation_without_whatsapp_redirect(): void
    {
        $catalog = $this->makeServiceCatalog();
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $response = $this->postJson(route('catalog.item.book', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-1',
        ]), [
            'slot_id' => $slots[0]['id'],
            'customerName' => 'Jane Doe',
            'customerPhone' => '254712345678',
            'notes' => 'Please call before arrival',
            'duration_minutes' => 30,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('requires_action', false);
        $response->assertJsonStructure(['reservation' => ['id', 'service', 'date_label', 'time_label']]);

        $this->assertDatabaseHas('rem_reservations', [
            'company_id' => $this->company->id,
            'source_id' => $this->source->id,
            'status' => 1,
        ]);
    }

    public function test_book_item_requires_slot_id(): void
    {
        $catalog = $this->makeServiceCatalog();

        $response = $this->postJson(route('catalog.item.book', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-1',
        ]), [
            'customerPhone' => '254712345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slot_id']);
    }

    public function test_slot_booking_with_flow_token_dispatches_flow_resume_job(): void
    {
        Queue::fake();

        $catalog = $this->makeServiceCatalog();
        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254712345670',
            'company_id' => $this->company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Service Flow',
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'listing-1',
                        'type' => 'listing_inquiry',
                        'data' => ['settings' => ['catalogId' => $catalog->id, 'completionType' => 'booking']],
                    ],
                ],
                'edges' => [],
            ]),
        ]);

        $flowToken = app(CatalogFlowCallbackService::class)->makeToken(
            $flow->id,
            $contact->id,
            'listing-1',
            $catalog->id
        );

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $response = $this->postJson(route('catalog.item.book', [
            'catalogId' => $catalog->id,
            'itemId' => 'service-1',
        ]), [
            'slot_id' => $slots[0]['id'],
            'customerName' => 'Jane Doe',
            'customerPhone' => '254712345678',
            'flow_token' => $flowToken,
        ]);

        $response->assertOk();
        Queue::assertPushed(ResumeFlowFromListingInquiry::class);
    }

    public function test_public_service_catalog_page_includes_slot_booking_ui(): void
    {
        $catalog = $this->makeServiceCatalog();

        $response = $this->get(route('catalog.public', $catalog->id));

        $response->assertOk();
        $response->assertSee('bookingSlotSection', false);
        $response->assertSee('bookingLoadingView', false);
        $response->assertSee('Opening...', false);
        $response->assertSee('Confirm booking', false);
        $response->assertSee('AC Service &amp; Gas Refill', false);
    }

    private function makeServiceCatalog(): ListCatalog
    {
        return ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id,
            'name' => 'Home Services',
            'slug' => 'home-services',
            'catalog_mode' => CatalogMode::SERVICE,
            'vertical' => 'general_service',
            'version' => 1,
            'items' => [[
                'id' => 'service-1',
                'title' => 'AC Service & Gas Refill',
                'description' => 'Full AC service',
                'price' => 3500,
                'metadata' => [
                    'booking_source_id' => $this->source->id,
                    'listing_status' => 'Available',
                ],
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);
    }
}
