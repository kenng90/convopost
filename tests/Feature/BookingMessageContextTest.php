<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingMessageContextService;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Template;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingMessageContextTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private BookingMessageContextService $context;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->context = app(BookingMessageContextService::class);
        config(['settings.enable_credits' => false]);
    }

    private function makeTemplate(): Template
    {
        return Template::create([
            'name' => 'booking_tpl_'.random_int(1, 9999),
            'language' => 'en',
            'status' => 'APPROVED',
            'company_id' => $this->company->id,
            'components' => json_encode([
                ['type' => 'BODY', 'text' => 'On {{1}} at {{2}} at {{3}}'],
            ]),
            'category' => 'UTILITY',
        ]);
    }

    private function makeReminderCampaign(Template $template): Campaign
    {
        return Campaign::create([
            'name' => 'Booking notice',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_reminder' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'variables' => json_encode([
                'body' => [
                    '1' => 'placeholder',
                    '2' => 'placeholder',
                    '3' => 'placeholder',
                ],
            ]),
            'variables_match' => json_encode([
                'body' => [
                    '1' => (string) BookingMessageContextService::FIELD_START_DATE,
                    '2' => (string) BookingMessageContextService::FIELD_START_TIME,
                    '3' => (string) BookingMessageContextService::FIELD_LOCATION,
                ],
            ]),
        ]);
    }

    public function test_reservation_context_includes_location_and_service(): void
    {
        $source = Source::create([
            'company_id' => $this->company->id,
            'name' => 'Dental',
            'timezone' => config('app.timezone', 'UTC'),
            'location' => 'Clinic A',
            'is_bookable' => true,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000100',
            'subscribed' => 1,
        ]);

        $start = Carbon::parse('2026-08-01 10:00:00', $source->timezone);

        $reservation = Reservation::create([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'source_id' => $source->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'status' => 1,
            'external_id' => 'REF-1',
        ]);

        $context = $this->context->forReservation($reservation->fresh(['source']));

        $this->assertSame('Clinic A', $context['location']);
        $this->assertSame('Dental', $context['service_name']);
        $this->assertSame('REF-1', $context['external_id']);
        $this->assertNotEmpty($context['start_date']);
        $this->assertNotEmpty($context['start_time']);
    }

    public function test_reservation_context_falls_back_to_hash_id_reference(): void
    {
        $source = Source::create([
            'company_id' => $this->company->id,
            'name' => 'Dental',
            'timezone' => config('app.timezone', 'UTC'),
            'location' => 'Clinic A',
            'is_bookable' => true,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000199',
            'subscribed' => 1,
        ]);

        $start = Carbon::parse('2026-08-01 10:00:00', $source->timezone);

        $reservation = Reservation::create([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'source_id' => $source->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'status' => 1,
            'external_id' => null,
        ]);

        $context = $this->context->forReservation($reservation->fresh(['source']));

        $this->assertSame('#'.$reservation->id, $context['external_id']);
    }

    public function test_before_reminder_keeps_appointment_date_not_send_time(): void
    {
        $template = $this->makeTemplate();
        $campaign = $this->makeReminderCampaign($template);

        $source = Source::create([
            'company_id' => $this->company->id,
            'name' => 'Consult',
            'timezone' => config('app.timezone', 'UTC'),
            'location' => 'Room 1',
            'is_bookable' => true,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000101',
            'subscribed' => 1,
        ]);

        $start = Carbon::parse('2026-09-10 15:00:00', $source->timezone);

        $reservation = new Reservation([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'source_id' => $source->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'status' => 1,
        ]);
        $reservation->saveQuietly();

        $reminder = Remineder::create([
            'company_id' => $this->company->id,
            'source_id' => $source->id,
            'campaign_id' => $campaign->id,
            'name' => '24h before',
            'type' => 1,
            'time' => 24,
            'time_type' => 'hours',
            'status' => 1,
        ]);

        $reservation = $reservation->fresh(['source', 'contact']);
        $context = $this->context->forReservation($reservation);
        $message = $this->context->sendReservationReminder($reminder, $reservation);

        $this->assertNotNull($message);
        $this->assertStringContainsString($context['start_date'], $message->value);
        $this->assertStringContainsString($context['start_time'], $message->value);
        $this->assertStringContainsString('Room 1', $message->value);

        $scheduled = Carbon::parse($message->scchuduled_at);
        $appointmentStart = Carbon::parse($reservation->start_date);

        // Send time is 24 hours before the appointment, not the appointment itself.
        $this->assertEqualsWithDelta(24, $scheduled->diffInHours($appointmentStart), 0.01);

        // Appointment date in the template must not equal the send-time date (mutation bug).
        $this->assertNotSame(
            $scheduled->timezone($source->timezone)->format('M j, Y'),
            $context['start_date']
        );
    }

    public function test_reservation_confirmation_is_sent_when_configured(): void
    {
        $template = $this->makeTemplate();
        $campaign = $this->makeReminderCampaign($template);

        $source = Source::create([
            'company_id' => $this->company->id,
            'name' => 'Consult',
            'timezone' => config('app.timezone', 'UTC'),
            'location' => 'Lobby',
            'confirmation_campaign_id' => $campaign->id,
            'is_bookable' => true,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000102',
            'subscribed' => 1,
        ]);

        $start = Carbon::parse('2026-10-01 09:00:00', $source->timezone);

        $reservation = Reservation::create([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'source_id' => $source->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addMinutes(30),
            'status' => 1,
        ]);

        $this->assertTrue($reservation->hasTemplateConfirmation());

        $message = Message::query()
            ->where('campaign_id', $campaign->id)
            ->where('extra', (string) $reservation->id)
            ->first();

        $context = $this->context->forReservation($reservation->fresh(['source']));

        $this->assertNotNull($message);
        $this->assertStringContainsString($context['start_date'], $message->value);
        $this->assertStringContainsString('Lobby', $message->value);
    }

    public function test_event_confirmation_includes_location(): void
    {
        $template = $this->makeTemplate();
        $campaign = $this->makeReminderCampaign($template);

        $event = Event::create([
            'company_id' => $this->company->id,
            'title' => 'Launch',
            'location' => 'Hall B',
            'timezone' => config('app.timezone', 'UTC'),
            'is_published' => true,
            'confirmation_campaign_id' => $campaign->id,
        ]);

        $start = Carbon::parse('2026-11-05 18:00:00', $event->timezone);

        $occurrence = EventOccurrence::create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000103',
            'subscribed' => 1,
        ]);

        $registration = EventRegistration::create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'contact_id' => $contact->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
            'party_size' => 1,
        ]);

        $message = Message::query()
            ->where('campaign_id', $campaign->id)
            ->where('extra', 'event_reg:'.$registration->id)
            ->first();

        $context = $this->context->forEventRegistration($registration->fresh(['event', 'occurrence']));

        $this->assertNotNull($message);
        $this->assertStringContainsString($context['start_date'], $message->value);
        $this->assertStringContainsString($context['start_time'], $message->value);
        $this->assertStringContainsString('Hall B', $message->value);
    }

    public function test_campaign_field_options_include_location(): void
    {
        $options = BookingMessageContextService::campaignFieldOptions();

        $this->assertArrayHasKey(BookingMessageContextService::FIELD_LOCATION, $options);
        $this->assertArrayHasKey(BookingMessageContextService::FIELD_START_DATE, $options);
        $this->assertArrayHasKey(BookingMessageContextService::FIELD_START_TIME, $options);
    }
}
