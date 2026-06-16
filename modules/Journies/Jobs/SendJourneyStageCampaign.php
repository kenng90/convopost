<?php

namespace Modules\Journies\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Journies\Models\JourneyActivity;
use Modules\Journies\Models\JourneyStage;
use Modules\Wpbox\Models\Contact;

class SendJourneyStageCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $contactId,
        public int $stageId,
        public ?int $activityId = null,
        public string $source = 'manual',
    ) {
    }

    public function handle(): void
    {
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);
        $stage = JourneyStage::withoutGlobalScopes()->find($this->stageId);

        if (! $contact || ! $stage || ! $stage->campaign_id) {
            return;
        }

        Log::info('Sending journey stage campaign', [
            'contact' => $contact->id,
            'stage' => $stage->id,
            'campaign' => $stage->campaign_id,
            'source' => $this->source,
        ]);

        try {
            $apiController = new \Modules\Wpbox\Http\Controllers\APIController();

            $request = new \Illuminate\Http\Request();
            $request->merge([
                'campaing_id' => $stage->campaign_id,
                'phone' => $contact->phone,
                'data' => [],
                'token' => '_',
            ]);

            $apiController->sendCampaignMessageToPhoneNumber($request);

            if ($this->activityId) {
                JourneyActivity::withoutGlobalScopes()
                    ->where('id', $this->activityId)
                    ->update(['campaign_status' => 'sent']);
            }
        } catch (\Throwable $e) {
            if ($this->activityId) {
                JourneyActivity::withoutGlobalScopes()
                    ->where('id', $this->activityId)
                    ->update(['campaign_status' => 'failed']);
            }

            Log::error('Journey campaign failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}
