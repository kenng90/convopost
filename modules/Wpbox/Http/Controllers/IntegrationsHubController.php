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
            'apiDocs' => \App\Http\Controllers\Api\V1\OpenApiController::documentationUrl(),
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
        $authenticator = app(\App\Services\Api\PublicApiAuthenticator::class);
        $auth = $authenticator->authenticate($request, true);

        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            $provided = $request->header('X-Store-Event-Token') ?? $request->input('token');
            $expected = config('wpbox.campaign_dispatch_token') ?: hash('sha256', config('app.key').':campaign-dispatch');

            if (is_string($provided) && hash_equals($expected, $provided)) {
                return \App\Services\Api\PublicApiResponse::error(
                    'gone',
                    'POST /webhook/wpbox/store-event no longer accepts the platform dispatch token. Use POST /api/v1/events with a company Bearer token.',
                    410
                );
            }

            return $auth;
        }

        $request->validate([
            'event_type' => 'required_without:event|string',
            'event' => 'required_without:event_type|string',
        ]);

        $company = $auth['company'];
        $type = (string) $request->input('event_type', $request->input('event'));
        $sent = $triggerService->fire($company, $type, $request->input('data', []));
        app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($company->id, $type, $request->input('data', []));

        return response()->json([
            'status' => 'ok',
            'triggered' => $sent,
            'company_id' => $company->id,
        ]);
    }
}
