<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\PublicApiResponse;
use App\Services\Api\PublicWebhookDispatcher;
use App\Services\Campaign\CampaignTriggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    public function store(
        Request $request,
        CampaignTriggerService $triggers,
        PublicWebhookDispatcher $webhooks
    ): JsonResponse {
        $request->validate([
            'event' => 'required_without:event_type|string',
            'event_type' => 'required_without:event|string',
            'data' => 'nullable|array',
        ]);

        $company = $request->attributes->get('public_api_company');
        $type = (string) $request->input('event', $request->input('event_type'));
        $data = $request->input('data', []);

        if (! is_array($data)) {
            $data = [];
        }

        $allowed = [
            CampaignTriggerService::EVENT_ORDER_CREATED,
            CampaignTriggerService::EVENT_CART_ABANDONED,
            CampaignTriggerService::EVENT_FULFILLMENT_SHIPPED,
        ];

        $triggered = 0;

        if (in_array($type, $allowed, true)) {
            $triggered = $triggers->fire($company, $type, $data);
        }

        $eventId = $webhooks->dispatch($company->id, $type, $data);

        return PublicApiResponse::success([
            'event' => $type,
            'event_id' => $eventId,
            'triggered_campaigns' => $triggered,
            'company_id' => $company->id,
        ], 202);
    }
}
