<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Campaign\CampaignTriggerService;
use Illuminate\Http\Request;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\CampaignTrigger;

class IntegrationsHubController extends Controller
{
    public function index()
    {
        $this->ownerAndStaffOnly();

        $apiCampaigns = Campaign::where('is_api', true)->pluck('name', 'id');
        $triggers = CampaignTrigger::with('campaign')->orderByDesc('id')->get();

        $events = [
            CampaignTriggerService::EVENT_ORDER_CREATED => __('Order created'),
            CampaignTriggerService::EVENT_CART_ABANDONED => __('Cart abandoned'),
            CampaignTriggerService::EVENT_FULFILLMENT_SHIPPED => __('Order shipped'),
        ];

        return view('wpbox::campaigns.integrations.index', [
            'apiCampaigns' => $apiCampaigns,
            'triggers' => $triggers,
            'events' => $events,
            'webhookUrl' => $this->getCompany()->getConfig('campaign_webhook_url', ''),
            'apiDocs' => config('wpbox.api_docs'),
        ]);
    }

    public function storeTrigger(Request $request, CampaignTriggerService $triggerService)
    {
        $this->ownerAndStaffOnly();

        $request->validate([
            'event_type' => 'required|string',
            'campaign_id' => 'required|integer',
        ]);

        $triggerService->registerTrigger(
            $this->getCompany(),
            $request->event_type,
            (int) $request->campaign_id,
            $request->input('config', [])
        );

        if ($request->filled('campaign_webhook_url')) {
            $company = $this->getCompany();
            $company->setConfig('campaign_webhook_url', $request->campaign_webhook_url);
        }

        return redirect()->route('campaigns.integrations')->withStatus(__('Integration trigger saved.'));
    }

    public function receiveStoreEvent(Request $request, CampaignTriggerService $triggerService)
    {
        $token = $request->header('X-Store-Event-Token') ?? $request->input('token');
        $expected = config('wpbox.campaign_dispatch_token') ?: hash('sha256', config('app.key').':campaign-dispatch');

        if (! is_string($token) || ! hash_equals($expected, $token)) {
            abort(403);
        }

        $request->validate([
            'company_id' => 'required|integer',
            'event_type' => 'required|string',
        ]);

        $company = \App\Models\Company::findOrFail($request->company_id);
        $sent = $triggerService->fire($company, $request->event_type, $request->input('data', []));

        return response()->json(['status' => 'ok', 'triggered' => $sent]);
    }
}
