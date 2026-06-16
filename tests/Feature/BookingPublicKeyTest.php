<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingPublicKeyService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingPublicKeyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Source $source;

    private string $bookingKey;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'secure-clinic',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $staffUser->assignRole('staff');

        $appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'whatsapp_phone' => '+254712345670',
            'is_active' => true,
        ]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Consultation',
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
            'appointment_staff_id' => $appointmentStaff->id,
            'is_active' => true,
        ]);

        $this->bookingKey = app(BookingPublicKeyService::class)->ensureKey($this->company);
    }

    public function test_public_catalog_loads_without_url_token(): void
    {
        $response = $this->get(route('reminders.booking.catalog', [
            'subdomain' => $this->company->subdomain,
        ]));

        $response->assertOk();
        $response->assertDontSee('?token=', false);
        $response->assertSee('Consultation');
    }

    public function test_public_widget_injects_booking_key_not_url_token(): void
    {
        $response = $this->get(route('reminders.booking.widget', [
            'subdomain' => $this->company->subdomain,
            'source' => 'Consultation',
        ]));

        $response->assertOk();
        $response->assertSee($this->bookingKey, false);
        $response->assertSee('bookingKey', false);
        $response->assertDontSee('?token=', false);
    }

    public function test_services_api_works_with_booking_key(): void
    {
        $response = $this->getJson('/api/reminders/services?'.http_build_query([
            'booking_key' => $this->bookingKey,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonFragment(['name' => 'Consultation']);
    }

    public function test_services_api_still_works_with_sanctum_token(): void
    {
        $token = $this->owner->createToken('integration')->plainTextToken;

        $response = $this->getJson('/api/reminders/services?'.http_build_query([
            'token' => $token,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonFragment(['name' => 'Consultation']);
    }

    public function test_availability_api_rejects_invalid_booking_key(): void
    {
        $date = now('UTC')->addDay()->toDateString();

        $response = $this->getJson('/api/reminders/availability?'.http_build_query([
            'booking_key' => 'bk_invalid_key',
            'source' => 'Consultation',
            'date' => $date,
            'duration_minutes' => 30,
        ]));

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid booking key');
    }

    public function test_availability_api_works_with_booking_key_without_logging_in_owner(): void
    {
        $date = now('UTC')->addDay()->toDateString();

        $response = $this->getJson('/api/reminders/availability?'.http_build_query([
            'booking_key' => $this->bookingKey,
            'source' => 'Consultation',
            'date' => $date,
            'duration_minutes' => 30,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['slots']);

        $this->assertGuest();
    }

    public function test_rotating_booking_key_invalidates_previous_key(): void
    {
        $oldKey = $this->bookingKey;
        $newKey = app(BookingPublicKeyService::class)->rotate($this->company);

        $this->assertNotSame($oldKey, $newKey);

        $this->getJson('/api/reminders/services?'.http_build_query([
            'booking_key' => $oldKey,
        ]))->assertUnauthorized();

        $this->getJson('/api/reminders/services?'.http_build_query([
            'booking_key' => $newKey,
        ]))->assertOk();
    }

    public function test_booking_key_does_not_grant_owner_session(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $response = $this->postJson('/api/reminders/reservation/makeReservation', [
            'booking_key' => $this->bookingKey,
            'phone' => '+254712345679',
            'name' => 'Public Guest',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ]);

        $response->assertCreated();
        $this->assertGuest();
    }

    public function test_owner_can_regenerate_booking_key_from_settings(): void
    {
        session(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->owner)
            ->post(route('reminders.booking-settings.regenerate-key'));

        $response->assertRedirect(route('reminders.booking-settings.index'));

        $storedKey = $this->company->fresh()->getConfig(BookingPublicKeyService::CONFIG_KEY);
        $this->assertNotSame($this->bookingKey, $storedKey);
        $this->assertStringStartsWith(BookingPublicKeyService::PREFIX, $storedKey);
    }
}
