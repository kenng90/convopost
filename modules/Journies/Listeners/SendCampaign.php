<?php

namespace Modules\Journies\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Journies\Events\ContactMovedToStage;

class SendCampaign
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ContactMovedToStage $event)
    {
        //Get the campaign
        Log::info('Contact moved to stage', ['contact' => $event->contact, 'stage' => $event->stage]);

        //Get the campaign
        $campaign = $event->stage->campaign_id;

        // Create API controller instance
        $apiController = new \Modules\Wpbox\Http\Controllers\APIController();
        
        // Create request with required parameters
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'campaing_id' => $campaign,
            'phone' => $event->contact->phone,
            'data' => [],
            'token' => '_'
        ]);

        // Call the method directly
        $response = $apiController->sendCampaignMessageToPhoneNumber($request);
        
        Log::info('Campaign sent', ['response' => $response]);
    }
}
