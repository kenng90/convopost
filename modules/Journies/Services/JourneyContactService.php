<?php

namespace Modules\Journies\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Modules\Contacts\Models\Contact;
use Modules\Journies\Events\ContactMovedToStage;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyActivity;
use Modules\Journies\Models\JourneyStage;

class JourneyContactService
{
    public function moveContactToStage(
        Contact $contact,
        JourneyStage $stage,
        string $source = 'manual',
        ?int $userId = null,
        bool $fireCampaign = true,
        bool $skipIfSameStage = true,
    ): array {
        $journey = $stage->journey;

        if ($contact->company_id !== $journey->company_id) {
            return ['success' => false, 'message' => __('Contact does not belong to this company.')];
        }

        $currentStage = $this->currentStageForContact($contact, $journey);

        if ($skipIfSameStage && $currentStage && (int) $currentStage->id === (int) $stage->id) {
            return [
                'success' => true,
                'message' => __('Contact is already in this stage.'),
                'skipped' => true,
                'stage' => $stage,
                'current_stage_id' => $currentStage->id,
            ];
        }

        foreach ($journey->stages as $journeyStage) {
            $journeyStage->contacts()->detach($contact->id);
        }

        $stage->contacts()->attach($contact->id);

        $activity = JourneyActivity::create([
            'company_id' => $journey->company_id,
            'journey_id' => $journey->id,
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'user_id' => $userId ?? Auth::id(),
            'action' => $currentStage ? JourneyActivity::ACTION_MOVED : JourneyActivity::ACTION_ADDED,
            'source' => $source,
            'from_stage_id' => $currentStage?->id,
            'campaign_id' => $stage->campaign_id,
            'campaign_status' => $stage->campaign_id && $fireCampaign ? 'queued' : null,
        ]);

        $campaignQueued = false;

        if ($fireCampaign && $stage->campaign_id) {
            event(new ContactMovedToStage($contact, $stage, $activity->id, $source));
            $campaignQueued = true;
        }

        return [
            'success' => true,
            'message' => $currentStage
                ? __('Contact moved to :stage.', ['stage' => $stage->name])
                : __('Contact added to :stage.', ['stage' => $stage->name]),
            'stage' => $stage->fresh(['campaign']),
            'previous_stage_id' => $currentStage?->id,
            'activity_id' => $activity->id,
            'campaign_queued' => $campaignQueued,
            'campaign_name' => $stage->campaign?->name,
        ];
    }

    public function removeContactFromJourney(Contact $contact, Journey $journey, string $source = 'manual', ?int $userId = null): array
    {
        $currentStage = $this->currentStageForContact($contact, $journey);

        if (! $currentStage) {
            return ['success' => false, 'message' => __('Contact is not in this journey.')];
        }

        foreach ($journey->stages as $stage) {
            $stage->contacts()->detach($contact->id);
        }

        JourneyActivity::create([
            'company_id' => $journey->company_id,
            'journey_id' => $journey->id,
            'stage_id' => null,
            'contact_id' => $contact->id,
            'user_id' => $userId ?? Auth::id(),
            'action' => JourneyActivity::ACTION_REMOVED,
            'source' => $source,
            'from_stage_id' => $currentStage->id,
        ]);

        return [
            'success' => true,
            'message' => __('Contact removed from journey.'),
        ];
    }

    public function currentStageForContact(Contact $contact, Journey $journey): ?JourneyStage
    {
        return JourneyStage::query()
            ->where('journey_id', $journey->id)
            ->whereHas('contacts', fn ($query) => $query->where('contacts.id', $contact->id))
            ->with('campaign')
            ->first();
    }

    public function enrollNewContactIfConfigured(Contact $contact): void
    {
        $company = Company::find($contact->company_id);

        if (! $company) {
            return;
        }

        $settings = JourneySettings::for($company);

        if (! $settings->isEnabled() || ! $settings->autoEnrollNewContacts()) {
            return;
        }

        $journeyId = $settings->defaultJourneyId();

        if (! $journeyId) {
            return;
        }

        $journey = Journey::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $journeyId)
            ->first();

        if (! $journey) {
            return;
        }

        $firstStage = $journey->stages()->orderBy('order')->orderBy('id')->first();

        if (! $firstStage) {
            return;
        }

        session(['company_id' => $company->id]);
        $this->moveContactToStage($contact, $firstStage, 'auto_enroll', null, true, true);
    }

    public function applyGroupRules(Contact $contact, int $groupId): void
    {
        $rules = \Modules\Journies\Models\JourneyGroupRule::query()
            ->where('company_id', $contact->company_id)
            ->where('group_id', $groupId)
            ->with('stage.journey')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->stage) {
                $this->moveContactToStage($contact, $rule->stage, 'auto_group', null, true, false);
            }
        }
    }
}
