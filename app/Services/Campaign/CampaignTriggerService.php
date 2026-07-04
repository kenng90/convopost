<?php

namespace App\Services\Campaign;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\CampaignTrigger;
use Modules\Wpbox\Models\Contact;

class CampaignTriggerService
{
    public const EVENT_ORDER_CREATED = 'order.created';

    public const EVENT_CART_ABANDONED = 'cart.abandoned';

    public const EVENT_FULFILLMENT_SHIPPED = 'fulfillment.shipped';

    public function __construct(
        private readonly CampaignDispatchService $dispatch,
    ) {
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    public function fire(Company $company, string $eventType, array $eventData = []): int
    {
        $triggers = CampaignTrigger::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->get();

        $sent = 0;

        foreach ($triggers as $trigger) {
            try {
                if ($this->processTrigger($trigger, $company, $eventData)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::error('Campaign trigger failed', [
                    'trigger_id' => $trigger->id,
                    'event' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function processTrigger(CampaignTrigger $trigger, Company $company, array $eventData): bool
    {
        $phone = $eventData['phone'] ?? $eventData['customer_phone'] ?? null;

        if (! $phone) {
            return false;
        }

        $campaign = Campaign::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $trigger->campaign_id)
            ->where('is_api', true)
            ->where('is_active', true)
            ->where('status', '!=', Campaign::STATUS_INACTIVE)
            ->first();

        if (! $campaign) {
            return false;
        }

        $contact = Contact::firstOrCreate(
            ['company_id' => $company->id, 'phone' => $phone],
            ['name' => $eventData['customer_name'] ?? $phone, 'subscribed' => 1]
        );

        $contact->extra_value = $eventData;
        $message = $campaign->makeMessages(null, $contact);

        if (! $message) {
            return false;
        }

        return app(CampaignDispatchService::class)->sendSynchronously($message);
    }

    public function registerTrigger(Company $company, string $eventType, int $campaignId, array $config = []): CampaignTrigger
    {
        return CampaignTrigger::updateOrCreate(
            [
                'company_id' => $company->id,
                'event_type' => $eventType,
                'campaign_id' => $campaignId,
            ],
            [
                'is_active' => true,
                'config' => $config,
            ]
        );
    }
}
