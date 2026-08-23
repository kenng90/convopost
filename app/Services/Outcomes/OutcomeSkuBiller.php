<?php

namespace App\Services\Outcomes;

use App\Models\Company;
use App\Models\OutcomeAttribution;
use App\Services\Billing\CreditCharger;
use App\Services\Integrations\PlatformEventBus;
use Modules\Wpbox\Models\Contact;

class OutcomeSkuBiller
{
    public function __construct(
        private readonly CreditCharger $charger,
        private readonly PlatformEventBus $events,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{billed: bool, attribution: OutcomeAttribution|null, credits: int, skipped?: string}
     */
    public function record(
        Company $company,
        ?Contact $contact,
        string $sku,
        string $event,
        float $revenue = 0,
        string $source = 'system',
        ?string $sourceId = null,
        array $metadata = [],
    ): array {
        $definition = $this->skuDefinition($sku);
        if (! $definition) {
            return ['billed' => false, 'attribution' => null, 'credits' => 0, 'skipped' => 'unknown_sku'];
        }

        $uniqueKey = $this->uniqueKey($sku, $contact, $source, $sourceId);

        $existing = OutcomeAttribution::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('unique_key', $uniqueKey)
            ->whereNull('voided_at')
            ->first();

        if ($existing) {
            return ['billed' => false, 'attribution' => $existing, 'credits' => 0, 'skipped' => 'duplicate'];
        }

        $action = (string) $definition['action'];
        $charged = $this->charger->charge($company, $action, $company->id);

        if (! $charged) {
            return ['billed' => false, 'attribution' => null, 'credits' => 0, 'skipped' => 'insufficient_credits'];
        }

        $creditCost = $this->actionCost($action);

        $attribution = OutcomeAttribution::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact?->id,
            'sku' => $sku,
            'event' => $event,
            'source' => $source,
            'unique_key' => $uniqueKey,
            'revenue' => $revenue,
            'credits_charged' => $creditCost,
            'billed_at' => now(),
            'guaranteed_until' => now()->addHours((int) config('outcome-playbooks.sku_billing.guarantee_hours', 48)),
            'metadata' => array_merge($metadata, [
                'action' => $action,
                'source_id' => $sourceId,
            ]),
        ]);

        $this->events->emit($company, 'outcome.billed', [
            'sku' => $sku,
            'event' => $event,
            'contact_id' => $contact?->id,
            'revenue' => $revenue,
            'credits' => $creditCost,
        ]);

        return ['billed' => true, 'attribution' => $attribution, 'credits' => $creditCost];
    }

    /**
     * @return array{voided: bool, attribution: OutcomeAttribution|null, credits_refunded: int}
     */
    public function void(Company $company, string $sku, string $source, ?string $sourceId, ?Contact $contact = null): array
    {
        $uniqueKey = $this->uniqueKey($sku, $contact, $source, $sourceId);

        $attribution = OutcomeAttribution::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('unique_key', $uniqueKey)
            ->whereNull('voided_at')
            ->first();

        if (! $attribution || ! $attribution->isVoidable()) {
            return ['voided' => false, 'attribution' => $attribution, 'credits_refunded' => 0];
        }

        $credits = (int) $attribution->credits_charged;
        if ($credits > 0 && $company->user) {
            $company->user->addCredits($credits, 'outcome_guarantee_'.$sku);
        }

        $attribution->update(['voided_at' => now()]);

        $this->events->emit($company, 'outcome.voided', [
            'sku' => $sku,
            'unique_key' => $uniqueKey,
            'credits_refunded' => $credits,
        ]);

        return ['voided' => true, 'attribution' => $attribution->fresh(), 'credits_refunded' => $credits];
    }

    /**
     * @return array<string, array{count: int, credits: int, revenue: float, voided: int}>
     */
    public function summaryForCompany(Company $company): array
    {
        $rows = OutcomeAttribution::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->get()
            ->groupBy('sku');

        $summary = [];
        foreach (array_keys(config('outcome-playbooks.sku_billing.skus', [])) as $sku) {
            $group = $rows->get($sku, collect());
            $active = $group->whereNull('voided_at');
            $summary[$sku] = [
                'count' => $active->count(),
                'credits' => (int) $active->sum('credits_charged'),
                'revenue' => (float) $active->sum('revenue'),
                'voided' => $group->whereNotNull('voided_at')->count(),
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function skuDefinition(string $sku): ?array
    {
        $definition = config("outcome-playbooks.sku_billing.skus.{$sku}");

        return is_array($definition) ? $definition : null;
    }

    private function uniqueKey(string $sku, ?Contact $contact, string $source, ?string $sourceId): string
    {
        return implode(':', array_filter([
            $sku,
            (string) ($contact?->id ?? 'none'),
            $source,
            $sourceId,
        ], fn ($part) => $part !== null && $part !== ''));
    }

    private function actionCost(string $action): int
    {
        foreach (config('credit-actions.actions', []) as $definition) {
            if (($definition['action'] ?? '') === $action) {
                return (int) ($definition['default_cost'] ?? 0);
            }
        }

        return 0;
    }
}
