<?php

namespace App\Services\Outcomes;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;
use Modules\Wpbox\Models\Contact;

class OutcomeJourneyEnroller
{
    public function __construct(
        private readonly JourneyContactService $journeyContacts,
    ) {
    }

    /**
     * Enroll or move a contact into an outcome playbook stage.
     */
    public function moveToPlaybookStage(
        Company $company,
        Contact $contact,
        string $playbookKey,
        string $stageName,
        string $source = 'outcome_automation',
    ): bool {
        $playbook = config("outcome-playbooks.playbooks.{$playbookKey}");
        if (! is_array($playbook)) {
            return false;
        }

        $journeyId = (int) $company->getConfig($playbook['config_journey_key'], 0);
        if (! $journeyId || $company->getConfig($playbook['config_installed_key'], 'no') !== 'yes') {
            return false;
        }

        $stage = JourneyStage::withoutGlobalScopes()
            ->where('journey_id', $journeyId)
            ->where('name', $stageName)
            ->first();

        if (! $stage) {
            $stage = JourneyStage::withoutGlobalScopes()
                ->where('journey_id', $journeyId)
                ->where('name', 'like', '%'.$stageName.'%')
                ->orderBy('order')
                ->first();
        }

        if (! $stage) {
            return false;
        }

        try {
            session(['company_id' => $company->id]);
            $result = $this->journeyContacts->moveContactToStage($contact, $stage, $source);

            return (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('Outcome journey enroll failed', [
                'playbook' => $playbookKey,
                'contact_id' => $contact->id,
                'stage' => $stageName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function enrollCartAbandoned(Company $company, string $phone, ?string $name = null): bool
    {
        $contact = Contact::firstOrCreate(
            ['company_id' => $company->id, 'phone' => $phone],
            ['name' => $name ?: $phone, 'subscribed' => 1]
        );

        $playbook = config('outcome-playbooks.playbooks.cart_recovery');
        $entry = $playbook['entry_stage'] ?? 'Abandoned';

        return $this->moveToPlaybookStage($company, $contact, 'cart_recovery', $entry, 'cart_abandoned');
    }

    public function markCartRecovered(Company $company, Contact $contact): bool
    {
        return $this->moveToPlaybookStage($company, $contact, 'cart_recovery', 'Recovered', 'cart_converted');
    }

    public function enrollBooking(Company $company, Contact $contact): bool
    {
        return $this->moveToPlaybookStage($company, $contact, 'booking_convert', 'Booked', 'booking_created');
    }

    public function markBookingNoShow(Company $company, Contact $contact): bool
    {
        return $this->moveToPlaybookStage($company, $contact, 'booking_convert', 'No-show', 'booking_no_show');
    }

    public function markBookingAttended(Company $company, Contact $contact): bool
    {
        return $this->moveToPlaybookStage($company, $contact, 'booking_convert', 'Attended', 'booking_attended');
    }

    public function resolvePlaybookJourney(Company $company, string $playbookKey): ?Journey
    {
        $playbook = config("outcome-playbooks.playbooks.{$playbookKey}");
        if (! is_array($playbook)) {
            return null;
        }

        $journeyId = (int) $company->getConfig($playbook['config_journey_key'], 0);

        return $journeyId
            ? Journey::withoutGlobalScopes()->where('company_id', $company->id)->find($journeyId)
            : null;
    }
}
