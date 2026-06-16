<?php

namespace Modules\Journies\Listeners;

use Modules\Journies\Events\ContactMovedToStage;
use Modules\Journies\Jobs\SendJourneyStageCampaign;

class DispatchJourneyCampaign
{
    public function handle(ContactMovedToStage $event): void
    {
        if (! $event->stage->campaign_id) {
            return;
        }

        $delayMinutes = (int) ($event->stage->campaign_delay_minutes ?? 0);

        $job = new SendJourneyStageCampaign(
            $event->contact->id,
            $event->stage->id,
            $event->activityId,
            $event->source,
        );

        if ($delayMinutes > 0) {
            dispatch($job)->delay(now()->addMinutes($delayMinutes));
        } else {
            dispatch($job);
        }
    }
}
