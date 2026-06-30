<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\BookingPublicKeyService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Support\BookingPaymentConfig;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersBookingPaymentTest extends TestCase
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
            'subdomain' => 'paid-clinic',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);

        session(['company_id' => $this->company->id]);

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
            'name' => 'Paid consult',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
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

    public function test_payment_config_requires_toggle_and_positive_amount(): void
    {
        $this->source->update([
            'payment_required' => true,
            'payment_amount' => 0,
        ]);

        $this->assertFalse(BookingPaymentConfig::fromSource($this->source->fresh())['payment_required']);

        $this->source->update(['payment_amount' => 1500]);

        $config = BookingPaymentConfig::fromSource($this->source->fresh());
        $this->assertTrue($config['payment_required']);
        $this->assertSame(1500.0, $config['payment_total_amount']);
        $this->assertSame(1500.0, $config['payment_amount']);
        $this->assertSame(100, $config['payment_upfront_percent']);
        $this->assertSame('KES', $config['payment_currency']);
    }

    public function test_upfront_percent_charges_partial_amount_for_appointments(): void
    {
        $this->source->update([
            'payment_required' => true,
            'payment_amount' => 2000,
            'payment_upfront_percent' => 25,
        ]);

        $config = BookingPaymentConfig::fromSource($this->source->fresh());

        $this->assertTrue($config['payment_required']);
        $this->assertSame(2000.0, $config['payment_total_amount']);
        $this->assertSame(25, $config['payment_upfront_percent']);
        $this->assertSame(500.0, $config['payment_amount']);

        $services = app(BookingCatalogService::class)->bookableServicesForCompany($this->company);

        $this->assertSame(500.0, (float) $services[0]['payment_amount']);
        $this->assertSame(2000.0, (float) $services[0]['payment_total_amount']);
        $this->assertSame(25, $services[0]['payment_upfront_percent']);
    }

    public function test_services_api_exposes_payment_fields(): void
    {
        $this->source->update([
            'payment_required' => true,
            'payment_amount' => 1200,
        ]);

        $services = app(BookingCatalogService::class)->bookableServicesForCompany($this->company);

        $this->assertTrue($services[0]['payment_required']);
        $this->assertSame(1200.0, (float) $services[0]['payment_amount']);
    }

    public function test_create_reservation_is_blocked_when_payment_required(): void
    {
        $this->source->update([
            'payment_required' => true,
            'payment_amount' => 1000,
        ]);

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $response = $this->postJson('/api/reminders/reservation/makeReservation', [
            'booking_key' => $this->bookingKey,
            'source' => 'Paid consult',
            'slot_id' => $slots[0]['id'],
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
        ]);

        $response->assertStatus(402)
            ->assertJsonPath('payment_required', true);
    }

    public function test_pay_appointment_endpoint_books_free_services_immediately(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $response = $this->postJson('/api/reminders/booking/pay/appointment', [
            'booking_key' => $this->bookingKey,
            'source' => 'Paid consult',
            'slot_id' => $slots[0]['id'],
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
        ]);

        $response->assertCreated()
            ->assertJsonPath('requires_action', false)
            ->assertJsonPath('reservation.payment_status', BookingPaymentConfig::STATUS_NOT_REQUIRED);

        $this->assertDatabaseHas('rem_reservations', [
            'source_id' => $this->source->id,
            'payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED,
        ]);
    }

    public function test_fulfilling_successful_booking_payment_creates_reservation(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $payload = [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Paid consult',
            'slot_id' => $slots[0]['id'],
        ];

        $records = Invoice::createForBookingPayment(
            company: $this->company,
            customerName: 'Jane Doe',
            customerPhone: '+254712345678',
            bookingType: BookingPaymentService::BOOKING_TYPE_APPOINTMENT,
            bookingPayload: $payload,
            amount: 1500,
            currency: 'KES',
            transactionDesc: 'Paid consult',
            accountReference: 'BK-APT',
        );

        /** @var InvoicePayment $payment */
        $payment = $records['payment'];
        $payment->update([
            'status' => 'success',
            'mpesa_checkout_request_id' => 'ws_CO_TEST123',
            'completed_at' => now(),
        ]);

        app(BookingPaymentService::class)->fulfillSuccessfulPayment($payment);

        $payment->invoice->refresh();
        $this->assertNotNull($payment->invoice->bookingNotes()['fulfilled_at']);
        $this->assertNotNull($payment->invoice->bookingNotes()['reservation_id']);

        $reservation = Reservation::find($payment->invoice->bookingNotes()['reservation_id']);
        $this->assertNotNull($reservation);
        $this->assertSame(BookingPaymentConfig::STATUS_PAID, $reservation->payment_status);
        $this->assertSame(1500.0, (float) $reservation->payment_amount);
        $this->assertSame(1500.0, (float) $reservation->payment_total_amount);
        $this->assertSame($payment->id, $reservation->invoice_payment_id);
    }

    public function test_fulfilling_partial_booking_payment_stores_total_and_paid_amounts(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $payload = [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Paid consult',
            'slot_id' => $slots[0]['id'],
        ];

        $records = Invoice::createForBookingPayment(
            company: $this->company,
            customerName: 'Jane Doe',
            customerPhone: '+254712345678',
            bookingType: BookingPaymentService::BOOKING_TYPE_APPOINTMENT,
            bookingPayload: $payload,
            amount: 500,
            currency: 'KES',
            transactionDesc: 'Paid consult',
            accountReference: 'BK-APT',
            paymentTotalAmount: 2000,
            paymentUpfrontPercent: 25,
        );

        /** @var InvoicePayment $payment */
        $payment = $records['payment'];
        $payment->update([
            'status' => 'success',
            'mpesa_checkout_request_id' => 'ws_CO_TEST456',
            'completed_at' => now(),
        ]);

        app(BookingPaymentService::class)->fulfillSuccessfulPayment($payment);

        $reservation = Reservation::find($payment->invoice->fresh()->bookingNotes()['reservation_id']);
        $this->assertNotNull($reservation);
        $this->assertSame(500.0, (float) $reservation->payment_amount);
        $this->assertSame(2000.0, (float) $reservation->payment_total_amount);
    }

    public function test_event_catalog_includes_payment_fields(): void
    {
        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Paid workshop',
            'timezone' => 'UTC',
            'is_published' => true,
            'payment_required' => true,
            'payment_amount' => 500,
        ]);

        EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(2),
            'ends_at' => now('UTC')->addDays(2)->addHour(),
            'capacity' => 20,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);

        $events = app(EventCatalogService::class)->publishedEventsForCompany($this->company);

        $this->assertTrue($events[0]['payment_required']);
        $this->assertSame(500.0, (float) $events[0]['payment_amount']);
    }

    public function test_register_for_event_is_blocked_when_payment_required(): void
    {
        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Paid workshop',
            'timezone' => 'UTC',
            'is_published' => true,
            'payment_required' => true,
            'payment_amount' => 500,
        ]);

        $occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(2),
            'ends_at' => now('UTC')->addDays(2)->addHour(),
            'capacity' => 20,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);

        $response = $this->postJson('/api/reminders/events/register', [
            'booking_key' => $this->bookingKey,
            'occurrence_id' => $occurrence->id,
            'name' => 'Alice',
            'phone' => '+254711111111',
        ]);

        $response->assertStatus(402)
            ->assertJsonPath('payment_required', true);
    }
}
