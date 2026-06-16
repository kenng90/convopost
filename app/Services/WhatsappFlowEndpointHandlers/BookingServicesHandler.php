<?php

namespace App\Services\WhatsappFlowEndpointHandlers;

use App\Models\Company;
use App\Models\WhatsappFlow;
use Modules\Reminders\Services\BookingCatalogService;

/**
 * Endpoint template: list bookable services for customer selection.
 */
class BookingServicesHandler
{
    public const TEMPLATE_KEY = 'booking_services';

    public function __construct(
        private readonly BookingCatalogService $catalogService
    ) {
    }

    public function supportsScreen(?WhatsappFlow $flow, ?string $screenId): bool
    {
        if (! $flow || ! $screenId) {
            return false;
        }

        foreach ($flow->flow_json['screens'] ?? [] as $screen) {
            if (($screen['id'] ?? '') === $screenId) {
                return ($screen['endpoint_template'] ?? '') === self::TEMPLATE_KEY;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function initData(?WhatsappFlow $flow = null): array
    {
        $company = $this->resolveCompany($flow);

        return [
            'is_service_list_visible' => true,
            'available_services' => $company
                ? $this->catalogService->bookableServicesAsFlowOptions($company)
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function handleDataExchange(string $screenId, array $data, ?WhatsappFlow $flow = null): ?array
    {
        return null;
    }

    private function resolveCompany(?WhatsappFlow $flow): ?Company
    {
        if (! $flow?->company_id) {
            return null;
        }

        return Company::find($flow->company_id);
    }
}
