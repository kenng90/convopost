<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingMessageContextService;
use Modules\Wpbox\Models\Campaign;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingReminderAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Source $source;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Massage',
            'is_bookable' => true,
            'timezone' => 'UTC',
        ]);

        $this->event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Workshop',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);
    }

    public function test_company_wide_event_reminder_does_not_apply_to_appointments(): void
    {
        $eventCampaign = $this->makeCampaign('Event reminder', [
            '1' => (string) BookingMessageContextService::FIELD_EVENT_TITLE,
        ]);

        $appointmentCampaign = $this->makeCampaign('Appointment reminder', [
            '1' => (string) BookingMessageContextService::FIELD_START_DATE,
        ]);

        $eventRule = Remineder::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Company-wide event reminder',
            'source_id' => null,
            'event_id' => null,
            'type' => 1,
            'time' => 1,
            'time_type' => 'hours',
            'campaign_id' => $eventCampaign->id,
            'status' => 1,
        ]);

        $appointmentRule = Remineder::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Company-wide appointment reminder',
            'source_id' => null,
            'event_id' => null,
            'type' => 1,
            'time' => 1,
            'time_type' => 'hours',
            'campaign_id' => $appointmentCampaign->id,
            'status' => 1,
        ]);

        $reservation = new Reservation([
            'company_id' => $this->company->id,
            'source_id' => $this->source->id,
        ]);

        $this->assertFalse($eventRule->appliesToReservation($reservation));
        $this->assertTrue($appointmentRule->appliesToReservation($reservation));
    }

    public function test_event_scoped_reminder_does_not_apply_to_appointments(): void
    {
        $campaign = $this->makeCampaign('Event reminder', [
            '1' => (string) BookingMessageContextService::FIELD_EVENT_TITLE,
        ]);

        $rule = Remineder::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Workshop reminder',
            'source_id' => null,
            'event_id' => $this->event->id,
            'type' => 1,
            'time' => 24,
            'time_type' => 'hours',
            'campaign_id' => $campaign->id,
            'status' => 1,
            'is_service_managed' => true,
        ]);

        $reservation = new Reservation([
            'company_id' => $this->company->id,
            'source_id' => $this->source->id,
        ]);

        $this->assertFalse($rule->appliesToReservation($reservation));
    }

    public function test_company_wide_appointment_reminder_does_not_apply_to_events(): void
    {
        $appointmentCampaign = $this->makeCampaign('Appointment reminder', [
            '1' => (string) BookingMessageContextService::FIELD_START_DATE,
        ]);

        $eventCampaign = $this->makeCampaign('Event reminder', [
            '1' => (string) BookingMessageContextService::FIELD_EVENT_TITLE,
        ]);

        $appointmentRule = Remineder::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'All services appointment reminder',
            'source_id' => null,
            'event_id' => null,
            'type' => 1,
            'time' => 1,
            'time_type' => 'hours',
            'campaign_id' => $appointmentCampaign->id,
            'status' => 1,
        ]);

        $eventRule = Remineder::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'All events reminder',
            'source_id' => null,
            'event_id' => null,
            'type' => 1,
            'time' => 1,
            'time_type' => 'hours',
            'campaign_id' => $eventCampaign->id,
            'status' => 1,
        ]);

        $registration = new EventRegistration([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
        ]);

        $this->assertFalse($appointmentRule->appliesToEventRegistration($registration));
        $this->assertTrue($eventRule->appliesToEventRegistration($registration));
    }

    public function test_service_managed_appointment_reminder_still_applies(): void
    {
        $campaign = $this->makeCampaign('Appointment reminder', [
            '1' => (string) BookingMessageContextService::FIELD_START_DATE,
        ]);

        $rule = Remineder::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Massage — client reminder (before)',
            'source_id' => $this->source->id,
            'event_id' => null,
            'type' => 1,
            'time' => 1,
            'time_type' => 'hours',
            'campaign_id' => $campaign->id,
            'status' => 1,
            'is_service_managed' => true,
        ]);

        $matching = new Reservation([
            'company_id' => $this->company->id,
            'source_id' => $this->source->id,
        ]);

        $otherSource = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Other',
            'is_bookable' => true,
            'timezone' => 'UTC',
        ]);

        $other = new Reservation([
            'company_id' => $this->company->id,
            'source_id' => $otherSource->id,
        ]);

        $this->assertTrue($rule->appliesToReservation($matching));
        $this->assertFalse($rule->appliesToReservation($other));
    }

    /**
     * @param  array<string, string>  $bodyMatch
     */
    private function makeCampaign(string $name, array $bodyMatch): Campaign
    {
        return Campaign::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'is_reminder' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'variables' => json_encode(['body' => array_map(fn () => 'placeholder', $bodyMatch)]),
            'variables_match' => json_encode(['body' => $bodyMatch]),
        ]);
    }
}
