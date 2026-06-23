<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicCatalogBrowseRequest;
use App\Models\CatalogCollection;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Catalog\CatalogAnalyticsService;
use App\Services\Catalog\CatalogCurrencyService;
use App\Services\Catalog\CatalogExperimentService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogInventoryService;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Catalog\CatalogWhatsAppOrderService;
use App\Services\CatalogItemFilterService;
use App\Services\InvoiceWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Flowmaker\Jobs\ResumeFlowFromCatalogCheckout;
use Modules\Invoice\Models\Invoice;
use RuntimeException;

class PublicCatalogController extends Controller
{
    public function __construct(
        protected CatalogItemFilterService $catalogItemFilter,
        protected CatalogAnalyticsService $catalogAnalyticsService,
        protected CatalogCurrencyService $catalogCurrencyService,
        protected CatalogFlowCallbackService $catalogFlowCallbackService,
        protected CatalogUrlService $catalogUrlService,
        protected CatalogInventoryService $catalogInventoryService,
        protected CatalogItemRepository $catalogItemRepository,
        protected CatalogExperimentService $catalogExperimentService,
        protected CatalogWhatsAppOrderService $catalogWhatsAppOrderService,
    ) {
    }

    public function showBySlug(PublicCatalogBrowseRequest $request, string $subdomain, string $slug)
    {
        $catalog = $this->catalogUrlService->resolveCatalog($subdomain, $slug);

        if (! $catalog) {
            return view('public.catalog.not-found', [
                'message' => 'Catalog not found',
            ]);
        }

        return $this->renderCatalog($request, $catalog);
    }

    public function showByExperiment(PublicCatalogBrowseRequest $request, string $subdomain, string $experimentKey)
    {
        $company = Company::where('subdomain', $subdomain)->first();
        if (! $company) {
            return view('public.catalog.not-found', ['message' => 'Catalog not found']);
        }

        $visitorKey = $request->cookie('catalog_visitor') ?? $request->session()->getId();
        $catalog = $this->catalogExperimentService->resolveCatalog($experimentKey, $company->id, $visitorKey);

        if (! $catalog) {
            return view('public.catalog.not-found', ['message' => 'Catalog experiment not found']);
        }

        return $this->renderCatalog($request, $catalog);
    }

    /**
     * Display public catalog page
     */
    public function show(PublicCatalogBrowseRequest $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return view('public.catalog.not-found', [
                'message' => 'Catalog not found',
            ]);
        }

        return $this->renderCatalog($request, $catalog);
    }

    /**
     * API endpoint to get catalog data (supports search, filters, pagination)
     */
    public function getItems(PublicCatalogBrowseRequest $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }

        $browse = $this->catalogItemFilter->browse(
            $this->catalogItemRepository->getItemsArray($catalog),
            $request->filters(),
            $this->catalogUrlService->publicUrl($catalog)
        );

        $paginator = $browse['items'];

        return response()->json([
            'success' => true,
            'catalog' => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'description' => $catalog->description,
            ],
            'items' => $paginator->items(),
            'filter_options' => $browse['filterOptions'],
            'filters' => $browse['filters'],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'total_in_catalog' => $browse['totalInCatalog'],
            'filtered_total' => $browse['filteredTotal'],
        ]);
    }

    public function trackEvent(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
        if (! $catalog) {
            return response()->json(['success' => false], 404);
        }

        $validated = $request->validate([
            'event' => 'required|string|in:view,cart_add,checkout_whatsapp,checkout_invoice',
            'metadata' => 'nullable|array',
        ]);

        $this->catalogAnalyticsService->record(
            $catalog->company_id,
            $catalog->id,
            $validated['event'],
            $validated['metadata'] ?? []
        );

        return response()->json(['success' => true]);
    }

    /**
     * Generate WhatsApp order message
     */
    public function generateOrder(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'customerName' => 'nullable|string|max:255',
                'customerPhone' => 'nullable|string',
                'notes' => 'nullable|string',
                'flow_token' => 'nullable|string',
            ]);

            $currency = $this->catalogCurrencyService->codeForCompany($catalog->company);

            $reservations = collect();

            try {
                $reservations = $this->reserveCheckoutInventory($catalog, $validated['items']);

                $orderMessage = '📦 *New Order from Catalog: '.$catalog->name."*\n\n";

                if ($validated['customerName'] ?? null) {
                    $orderMessage .= '👤 *Customer:* '.$validated['customerName']."\n";
                }

                if ($validated['customerPhone'] ?? null) {
                    $orderMessage .= '📱 *Phone:* '.$validated['customerPhone']."\n";
                }

                $orderMessage .= "\n📋 *Items:*\n";
                $totalPrice = 0;

                foreach ($validated['items'] as $item) {
                    $product = $this->findProductInCatalog($catalog->items, $item['id']);
                    if ($product) {
                        $itemTotal = ($product['price'] ?? 0) * $item['quantity'];
                        $orderMessage .= "• {$product['title']} (x{$item['quantity']}) - ";
                        if (isset($product['price'])) {
                            $orderMessage .= $this->catalogCurrencyService->formatAmount($catalog->company, (float) $itemTotal)."\n";
                            $totalPrice += $itemTotal;
                        } else {
                            $orderMessage .= "\n";
                        }
                    }
                }

                if ($totalPrice > 0) {
                    $orderMessage .= "\n💰 *Total:* ".$this->catalogCurrencyService->formatAmount($catalog->company, $totalPrice)."\n";
                }

                if ($validated['notes'] ?? null) {
                    $orderMessage .= "\n📝 *Notes:* ".$validated['notes']."\n";
                }

                $this->catalogAnalyticsService->record(
                    $catalog->company_id,
                    $catalog->id,
                    'checkout_whatsapp',
                    ['item_count' => count($validated['items']), 'total' => $totalPrice]
                );

                $this->maybeResumeFlowAfterWhatsAppCheckout($validated['flow_token'] ?? null, $catalog->id, $validated['items']);

                $this->commitCheckoutReservations($reservations);

                return response()->json([
                    'success' => true,
                    'message' => $orderMessage,
                    'orderData' => $validated,
                    'currency' => $currency,
                ]);
            } catch (\Throwable $e) {
                $this->releaseCheckoutReservations($reservations);
                throw $e;
            }

        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating order: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Generate invoice from cart order
     */
    public function createInvoice(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'items' => 'required|array',
                'customerPhone' => 'required|string|max:20',
                'deliveryAddress' => 'required|string|max:1000',
                'amount' => 'required|numeric|min:1',
                'notes' => 'nullable|string',
                'flow_token' => 'nullable|string',
            ]);

            $invoiceItems = [];
            $totalAmount = 0;

            foreach ($validated['items'] as $cartItem) {
                $product = $this->findProductInCatalog($catalog->items, $cartItem['id']);
                if ($product) {
                    $itemPrice = floatval($product['price'] ?? 0);
                    $itemTotal = $itemPrice * $cartItem['quantity'];
                    $totalAmount += $itemTotal;

                    $invoiceItems[] = [
                        'id' => $cartItem['id'],
                        'title' => $product['title'] ?? 'Unknown',
                        'description' => $product['description'] ?? '',
                        'price' => $itemPrice,
                        'quantity' => $cartItem['quantity'],
                        'variant' => $cartItem['variant'] ?? null,
                        'total' => $itemTotal,
                    ];
                }
            }

            if (abs($totalAmount - floatval($validated['amount'])) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount mismatch. Please refresh and try again.',
                ], 400);
            }

            $currency = $this->catalogCurrencyService->codeForCompany($catalog->company);

            $reservations = collect();

            try {
                $reservations = $this->reserveCheckoutInventory($catalog, $validated['items']);

                $invoice = Invoice::create([
                    'company_id' => $catalog->company_id,
                    'catalog_id' => $catalog->id,
                    'invoice_number' => Invoice::generateInvoiceNumber($catalog->company),
                    'customer_name' => 'Customer',
                    'customer_phone' => $validated['customerPhone'],
                    'customer_email' => null,
                    'delivery_address' => trim($validated['deliveryAddress']),
                    'amount' => $totalAmount,
                    'currency' => $currency,
                    'status' => 'draft',
                    'description' => $validated['notes'] ?? null,
                    'items' => $invoiceItems,
                ]);

                foreach ($reservations as $reservation) {
                    $reservation->update([
                        'reference_type' => Invoice::class,
                        'reference_id' => $invoice->id,
                    ]);
                }

                $this->commitCheckoutReservations($reservations);

                $whatsAppService = new InvoiceWhatsAppService($catalog->company);
                $whatsAppSent = $whatsAppService->sendInvoice($invoice);

                if ($whatsAppSent) {
                    $invoice->markAsSent();
                }

                $invoice->refresh();
                $invoiceIdentifier = $invoice->public_uuid ?? $invoice->id;

                $this->catalogAnalyticsService->record(
                    $catalog->company_id,
                    $catalog->id,
                    'checkout_invoice',
                    ['item_count' => count($validated['items']), 'total' => $totalAmount, 'invoice_id' => $invoice->id]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Invoice created successfully'.($whatsAppSent ? ' and sent via WhatsApp' : ''),
                    'invoice' => [
                        'id' => $invoiceIdentifier,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => (float) $invoice->amount,
                        'customer_phone' => $invoice->customer_phone,
                        'status' => $invoice->status,
                        'whatsapp_sent' => $whatsAppSent,
                    ],
                ]);
            } catch (\Throwable $e) {
                $this->releaseCheckoutReservations($reservations);
                throw $e;
            }

        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating invoice: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get invoice details (public endpoint for payment page)
     */
    public function getInvoice($invoiceId)
    {
        $invoice = Invoice::where('public_uuid', $invoiceId)
            ->orWhere('id', $invoiceId)
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $invoice->public_uuid ?? $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer_name,
                'customer_phone' => $invoice->customer_phone,
                'amount' => (float) $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'items' => $invoice->items,
                'total_paid' => $invoice->getTotalPaidAmount(),
                'remaining' => $invoice->getRemainingAmount(),
                'company' => [
                    'id' => $invoice->company->id,
                    'name' => $invoice->company->name,
                ],
            ],
        ]);
    }

    /**
     * Display invoice payment page
     */
    public function showInvoice($invoiceId)
    {
        $invoice = Invoice::where('public_uuid', $invoiceId)
            ->orWhere('id', $invoiceId)
            ->first();

        if (! $invoice) {
            return view('invoice.not-found', [
                'message' => 'Invoice not found',
            ]);
        }

        return view('invoice.payment', [
            'invoice' => [
                'id' => $invoice->public_uuid ?? $invoice->id,
                'public_uuid' => $invoice->public_uuid,
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $invoice->customer_name,
                'customer_phone' => $invoice->customer_phone,
                'customer_email' => $invoice->customer_email,
                'amount' => (float) $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'items' => $invoice->items,
                'total_paid' => $invoice->getTotalPaidAmount(),
                'remaining' => $invoice->getRemainingAmount(),
                'sent_at' => $invoice->sent_at,
                'company' => [
                    'id' => $invoice->company->id,
                    'name' => $invoice->company->name,
                ],
            ],
        ]);
    }

    private function renderCatalog(PublicCatalogBrowseRequest $request, ListCatalog $catalog)
    {
        $company = $catalog->company;
        $publicUrl = $this->catalogUrlService->publicUrl($catalog, $company);
        $catalogItems = $this->catalogItemRepository->getItemsArray($catalog);

        if ($collectionSlug = $request->query('collection')) {
            $collection = CatalogCollection::withoutGlobalScopes()
                ->where('company_id', $catalog->company_id)
                ->where('slug', $collectionSlug)
                ->where('is_active', true)
                ->first();

            if ($collection) {
                $allowedIds = $collection->items()->pluck('item_id')->all();
                $catalogItems = array_values(array_filter(
                    $catalogItems,
                    fn (array $item) => in_array($item['id'] ?? '', $allowedIds, true)
                ));
            }
        }

        $browse = $this->catalogItemFilter->browse(
            $catalogItems,
            $request->filters(),
            $publicUrl
        );

        $this->catalogAnalyticsService->record($catalog->company_id, $catalog->id, 'view');

        $currencyCode = $this->catalogCurrencyService->codeForCompany($company);
        $currencySymbol = $this->catalogCurrencyService->symbolForCode($currencyCode);
        $flowToken = $request->query('flow_token');

        return view('public.catalog.index', [
            'catalog' => $catalog,
            'company' => $company,
            'items' => $browse['items'],
            'filterOptions' => $browse['filterOptions'],
            'filters' => $browse['filters'],
            'totalInCatalog' => $browse['totalInCatalog'],
            'filteredTotal' => $browse['filteredTotal'],
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'flowToken' => is_string($flowToken) ? $flowToken : null,
            'whatsappOrderNumber' => $this->catalogWhatsAppOrderService->resolveNumber($company),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     */
    private function maybeResumeFlowAfterWhatsAppCheckout(?string $flowToken, int $catalogId, array $cartItems): void
    {
        if (! $flowToken) {
            return;
        }

        $context = $this->catalogFlowCallbackService->decodeToken($flowToken);
        if (! $context || (int) $context['catalog_id'] !== $catalogId) {
            return;
        }

        if ($cartItems === []) {
            return;
        }

        ResumeFlowFromCatalogCheckout::dispatch(
            $context['flow_id'],
            $context['contact_id'],
            CatalogFlowCallbackService::CHECKOUT_COMPLETE_EXTRA,
            $cartItems
        )->onQueue('flows');
    }

    private function findProductInCatalog($items, $productId)
    {
        if (! is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (($item['id'] ?? null) === $productId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: string, quantity: int}>  $cartItems
     */
    private function reserveCheckoutInventory(ListCatalog $catalog, array $cartItems): Collection
    {
        return $this->catalogInventoryService->reserveForCart($catalog, $cartItems);
    }

    private function commitCheckoutReservations(Collection $reservations): void
    {
        foreach ($reservations as $reservation) {
            $this->catalogInventoryService->commitReservation($reservation);
        }
    }

    private function releaseCheckoutReservations(Collection $reservations): void
    {
        foreach ($reservations as $reservation) {
            $this->catalogInventoryService->releaseReservation($reservation);
        }
    }
}
