<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1BookingsAndInvoicesTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_bookings_can_be_created_with_idempotency(): void
    {
        $this->createPublicApiOwner();
        Role::firstOrCreate(['name' => 'staff']);

        $staffUser = \App\Models\User::factory()->create(['company_id' => $this->apiCompany->id]);
        $staffUser->assignRole('staff');

        $appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'whatsapp_phone' => '+254712345670',
            'is_active' => true,
        ]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->apiCompany->id,
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
            'source_id' => $source->id,
            'appointment_staff_id' => $appointmentStaff->id,
            'is_active' => true,
        ]);

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($source, $date, 30);
        $this->assertNotEmpty($slots);

        $payload = [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ];

        $first = $this->postJson('/api/v1/bookings', $payload, $this->publicApiHeaders([
            'Idempotency-Key' => 'booking-1',
        ]));

        $first->assertCreated();
        $bookingId = $first->json('data.id');

        $replay = $this->postJson('/api/v1/bookings', $payload, $this->publicApiHeaders([
            'Idempotency-Key' => 'booking-1',
        ]));

        $replay->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $bookingId);

        $this->getJson('/api/v1/bookings/'.$bookingId, $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $bookingId);
    }

    public function test_invoices_can_be_created_without_whatsapp_send(): void
    {
        $this->createPublicApiOwner();

        $response = $this->postJson('/api/v1/invoices', [
            'customer_phone' => '+254700000500',
            'customer_name' => 'Payee',
            'amount' => 1500,
            'currency' => 'KES',
            'send_whatsapp' => false,
        ], $this->publicApiHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.invoice.amount', 1500)
            ->assertJsonPath('data.whatsapp_sent', false);

        $invoice = Invoice::query()->where('company_id', $this->apiCompany->id)->first();
        $this->assertNotNull($invoice);

        $this->getJson('/api/v1/invoices/'.$invoice->public_uuid, $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.public_uuid', $invoice->public_uuid);
    }
}
