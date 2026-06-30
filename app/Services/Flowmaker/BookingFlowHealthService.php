<?php

namespace App\Services\Flowmaker;

use App\Models\Company;
use App\Models\ListCatalog;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\EventCatalogService;

class BookingFlowHealthService
{
    private const LIST_LIMIT = 10;

    /**
     * @param  array<string, mixed>  $flowData
     * @return array<int, string>
     */
    public function validateForCompany(Company $company, array $flowData): array
    {
        $warnings = [];
        $nodes = $flowData['nodes'] ?? [];
        $edges = $flowData['edges'] ?? [];

        $serviceCount = count(app(BookingCatalogService::class)->bookableServicesForCompany($company));
        $mpesaConfigured = app(BookingPaymentService::class)->mpesaConfigured($company);
        $eventsEnabled = app(EventCatalogService::class)->eventsEnabled($company);

        foreach ($nodes as $node) {
            $id = $node['id'] ?? 'unknown';
            $type = $node['type'] ?? ($node['data']['type'] ?? null);
            $settings = $node['data']['settings'] ?? [];

            if ($type === 'book_appointment') {
                if ($serviceCount === 0) {
                    $warnings[] = "Book appointment node [{$id}]: no bookable services configured in Reminders.";
                }

                $fixedService = trim((string) ($settings['source_name'] ?? ''));
                if ($fixedService === '' && $serviceCount > self::LIST_LIMIT) {
                    $warnings[] = "Book appointment node [{$id}]: {$serviceCount} services exceed WhatsApp list limit (".self::LIST_LIMIT.'). Only the first '.self::LIST_LIMIT.' appear; use a fixed service or pagination.';
                }

                if (! $this->handleConnected($edges, $id, 'error')) {
                    $warnings[] = "Book appointment node [{$id}]: wire the Error output for payment and booking failures.";
                }

                if (! $this->handleConnected($edges, $id, 'unavailable')) {
                    $warnings[] = "Book appointment node [{$id}]: wire the Unavailable output for no open dates.";
                }

                if ($this->serviceRequiresPayment($company, $fixedService) && ! $mpesaConfigured) {
                    $warnings[] = "Book appointment node [{$id}]: selected service requires payment but M-Pesa is not configured.";
                }
            }

            if ($type === 'booking_events_list') {
                if (! $eventsEnabled) {
                    $warnings[] = "List events node [{$id}]: events booking is disabled for this account.";
                }

                if (! $this->hasDownstreamRegisterNode($nodes, $edges, $id)) {
                    $warnings[] = "List events node [{$id}]: connect Selected output to a Register for event node.";
                }

                if (! $this->handleConnected($edges, $id, 'empty')) {
                    $warnings[] = "List events node [{$id}]: wire the Empty output when no events are published.";
                }
            }

            if ($type === 'booking_event_register') {
                $occurrenceId = trim((string) ($settings['occurrence_id'] ?? ''));
                if ($occurrenceId === '' && ! $this->hasUpstreamListNode($nodes, $edges, $id)) {
                    $warnings[] = "Register for event node [{$id}]: pick a fixed session or wire after a List events node.";
                }

                if (! $this->handleConnected($edges, $id, 'error')) {
                    $warnings[] = "Register for event node [{$id}]: wire the Error output for registration failures.";
                }
            }

            if ($type === 'listing_inquiry') {
                $bookingBackend = (string) ($settings['bookingBackend'] ?? 'whatsapp_only');
                $catalogId = $settings['catalogId'] ?? '';

                if ($bookingBackend === 'reminders' && $catalogId !== '') {
                    $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
                    if ($catalog) {
                        $unlinked = $this->countUnlinkedListingItems($catalog);
                        if ($unlinked > 0) {
                            $warnings[] = "Listing inquiry node [{$id}]: {$unlinked} listing item(s) lack a linked bookable service (Reminders backend).";
                        }
                    }
                }
            }

            if ($type === 'send_booking_link' && trim((string) ($settings['link_type'] ?? '')) === '') {
                $warnings[] = "Send booking link node [{$id}]: choose a link type (appointments, events, or service).";
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function handleConnected(array $edges, string $nodeId, string $handle): bool
    {
        return collect($edges)->contains(function ($edge) use ($nodeId, $handle) {
            return ($edge['source'] ?? '') === $nodeId
                && ($edge['sourceHandle'] ?? '') === $handle
                && ! empty($edge['target']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function hasDownstreamRegisterNode(array $nodes, array $edges, string $listNodeId): bool
    {
        $targets = collect($edges)
            ->filter(fn ($e) => ($e['source'] ?? '') === $listNodeId && ($e['sourceHandle'] ?? '') === 'selected')
            ->pluck('target')
            ->all();

        foreach ($targets as $targetId) {
            $target = collect($nodes)->firstWhere('id', $targetId);
            $targetType = $target['type'] ?? ($target['data']['type'] ?? null);
            if ($targetType === 'booking_event_register') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function hasUpstreamListNode(array $nodes, array $edges, string $registerNodeId): bool
    {
        $sources = collect($edges)
            ->filter(fn ($e) => ($e['target'] ?? '') === $registerNodeId)
            ->pluck('source')
            ->all();

        foreach ($sources as $sourceId) {
            $source = collect($nodes)->firstWhere('id', $sourceId);
            $sourceType = $source['type'] ?? ($source['data']['type'] ?? null);
            if ($sourceType === 'booking_events_list') {
                return true;
            }
        }

        return false;
    }

    private function serviceRequiresPayment(Company $company, string $fixedServiceName): bool
    {
        foreach (app(BookingCatalogService::class)->bookableServicesForCompany($company) as $service) {
            if ($fixedServiceName !== '' && ($service['name'] ?? '') !== $fixedServiceName) {
                continue;
            }

            if (! empty($service['payment_required'])) {
                return true;
            }
        }

        return false;
    }

    private function countUnlinkedListingItems(ListCatalog $catalog): int
    {
        $count = 0;

        foreach ($catalog->items ?? [] as $item) {
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $sourceId = $metadata['booking_source_id'] ?? $item['booking_source_id'] ?? null;
            if ($sourceId === null || $sourceId === '') {
                $count++;
            }
        }

        return $count;
    }
}
