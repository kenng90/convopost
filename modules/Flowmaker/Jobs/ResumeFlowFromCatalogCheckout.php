<?php

namespace Modules\Flowmaker\Jobs;

use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogCheckoutVariableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class ResumeFlowFromCatalogCheckout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @param  list<array<string, mixed>>  $cartItems
     */
    public function __construct(
        public int $flowId,
        public int $contactId,
        public string $productId,
        public array $cartItems = [],
        public ?string $nodeId = null,
        public ?string $orderMessage = null,
        public int $catalogId = 0,
    ) {
    }

    public function handle(CatalogCheckoutVariableService $checkoutVariableService): void
    {
        $flow = Flow::withoutGlobalScopes()->find($this->flowId);
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);

        if (! $flow || ! $contact) {
            return;
        }

        if ($this->cartItems !== [] && $this->catalogId > 0) {
            $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->find($this->catalogId);

            if ($catalog) {
                $prefix = $this->nodeId
                    ? $checkoutVariableService->resolvePrefixFromFlowNode($flow, $this->nodeId)
                    : CatalogCheckoutVariableService::DEFAULT_PREFIX;

                $checkoutVariableService->storeOnContact(
                    $contact,
                    $flow->id,
                    $prefix,
                    $catalog,
                    $this->cartItems,
                    $this->orderMessage
                );
            }
        }

        $flow->resumeFromCatalogCheckout($contact, $this->productId, $this->cartItems);
    }
}
