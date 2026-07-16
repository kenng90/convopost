<?php

namespace Modules\Reminders\Services;

use App\Contracts\WhatsappFlowDataExchangeHandler;
use App\Services\WhatsappFlows\WhatsappFlowDataExchangeContext;

class BookingFlowDataExchangeHandler implements WhatsappFlowDataExchangeHandler
{
    public function __construct(
        private readonly BookingFlowDataExchangeService $bookingService,
    ) {
    }

    public function keys(): array
    {
        return ['booking_catalog', 'booking_slots', 'booking_occurrences'];
    }

    public function formBundleKeys(): array
    {
        return ['appointment_booking', 'healthcare_appointment', 'registration'];
    }

    public function handle(WhatsappFlowDataExchangeContext $context): ?array
    {
        if (! $context->flow || ! $context->endpointTemplate) {
            return null;
        }

        return $this->bookingService->resolve(
            $context->flow,
            $context->screenId,
            $context->endpointTemplate,
            $context->data,
        );
    }

    public function initData(WhatsappFlowDataExchangeContext $context): array
    {
        if (! $context->company || ! $context->endpointTemplate) {
            return [];
        }

        return $this->bookingService->initPayload($context->company, $context->endpointTemplate);
    }
}
