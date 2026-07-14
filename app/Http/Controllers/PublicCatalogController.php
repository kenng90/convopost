<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicCatalogBookItemRequest;
use App\Http\Requests\PublicCatalogBrowseRequest;
use App\Models\CatalogCollection;
use App\Models\CatalogOrder;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Catalog\CatalogAnalyticsService;
use App\Services\Catalog\CatalogBookingPendingService;
use App\Services\Catalog\CatalogCartSessionService;
use App\Services\Catalog\CatalogCheckoutPendingService;
use App\Services\Catalog\CatalogCurrencyService;
use App\Services\Catalog\CatalogExperimentService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogFlowNodeSettingsService;
use App\Services\Catalog\CatalogInventoryService;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogListingBookingReservationService;
use App\Services\Catalog\CatalogListingBookingService;
use App\Services\Catalog\CatalogListingInquiryService;
use App\Services\Catalog\CatalogListingSlotBookingService;
use App\Services\Catalog\CatalogOrderService;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Catalog\CatalogWhatsAppOrderService;
use App\Services\CatalogItemFilterService;
use App\Services\InvoiceWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Invoice\Models\Invoice;
use Modules\Reminders\Services\BookingPaymentService;
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
        protected CatalogListingInquiryService $catalogListingInquiryService,
        protected CatalogCheckoutPendingService $catalogCheckoutPendingService,
        protected CatalogListingBookingService $catalogListingBookingService,
        protected CatalogBookingPendingService $catalogBookingPendingService,
        protected CatalogFlowNodeSettingsService $catalogFlowNodeSettingsService,
        protected CatalogListingBookingReservationService $catalogListingBookingReservationService,
        protected CatalogListingSlotBookingService $catalogListingSlotBookingService,
        protected BookingPaymentService $bookingPaymentService,
        protected CatalogOrderService $catalogOrderService,
        protected CatalogCartSessionService $catalogCartSessionService,
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
            $this->catalogUrlService->publicUrl($catalog),
            $catalog->presentation()
        );

        $paginator = $browse['items'];

        return response()->json([
            'success' => true,
            'catalog' => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'description' => $catalog->description,
                'catalog_mode' => $catalog->resolvedCatalogMode(),
                'vertical' => $catalog->resolvedVertical(),
                'presentation' => $catalog->presentation(),
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
            'event' => 'required|string|in:view,cart_add,checkout_whatsapp,checkout_invoice,listing_inquiry,listing_booking',
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
                'visitor_key' => 'nullable|string|max:64',
                'deliveryAddress' => 'nullable|string|max:1000',
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

                $order = $this->catalogOrderService->createFromCheckout(
                    $catalog,
                    $validated['items'],
                    $catalog->items ?? [],
                    CatalogOrder::CHANNEL_WHATSAPP,
                    [
                        'customer_name' => $validated['customerName'] ?? null,
                        'customer_phone' => $validated['customerPhone'] ?? null,
                        'delivery_address' => $validated['deliveryAddress'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'flow_token' => $validated['flow_token'] ?? null,
                        'order_message' => $orderMessage,
                    ]
                );

                $this->catalogAnalyticsService->record(
                    $catalog->company_id,
                    $catalog->id,
                    'checkout_whatsapp',
                    ['item_count' => count($validated['items']), 'total' => $totalPrice, 'order_id' => $order->id]
                );

                $this->catalogCheckoutPendingService->storePendingFromFlowToken(
                    $validated['flow_token'] ?? null,
                    $catalog->id,
                    $validated['items'],
                    $orderMessage
                );

                if (! empty($validated['visitor_key'])) {
                    $this->catalogCartSessionService->markConverted($catalog, (string) $validated['visitor_key']);
                }

                $this->commitCheckoutReservations($reservations);

                return response()->json([
                    'success' => true,
                    'message' => $orderMessage,
                    'orderData' => $validated,
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'public_uuid' => $order->public_uuid,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                    ],
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
     * Build a WhatsApp inquiry for a listing or service item.
     */
    public function generateInquiry(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }

        if ($catalog->isCommerce()) {
            return response()->json([
                'success' => false,
                'message' => 'Inquiry is only available for listing and service catalogs.',
            ], 422);
        }

        try {
            $validated = $request->validate([
                'item_id' => 'required|string',
                'customerName' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
                'flow_token' => 'nullable|string',
            ]);

            $item = $this->findProductInCatalog($catalog->items, $validated['item_id']);
            if (! $item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in catalog.',
                ], 404);
            }

            $message = $this->catalogListingInquiryService->buildMessage(
                $catalog,
                $item,
                $validated['customerName'] ?? null,
                $validated['notes'] ?? null
            );

            $whatsappUrl = $this->catalogListingInquiryService->buildWhatsAppUrl(
                $catalog->company,
                $catalog,
                $item,
                $validated['customerName'] ?? null,
                $validated['notes'] ?? null
            );

            $this->catalogAnalyticsService->record(
                $catalog->company_id,
                $catalog->id,
                'listing_inquiry',
                ['item_id' => $validated['item_id']]
            );

            $this->catalogBookingPendingService->storePendingFromFlowToken(
                $validated['flow_token'] ?? null,
                $catalog->id,
                $item,
                [
                    'customerName' => $validated['customerName'] ?? null,
                    'customerPhone' => null,
                    'preferredDateTime' => null,
                    'notes' => $validated['notes'] ?? null,
                    'completionType' => 'inquiry',
                ],
                $message
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'whatsapp_url' => $whatsappUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating inquiry: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Build a WhatsApp booking request for a listing or service item.
     */
    public function generateBooking(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }

        if ($catalog->isCommerce()) {
            return response()->json([
                'success' => false,
                'message' => 'Booking is only available for listing and service catalogs.',
            ], 422);
        }

        try {
            $validated = $request->validate([
                'item_id' => 'required|string',
                'customerName' => 'nullable|string|max:255',
                'customerPhone' => 'required|string|max:30',
                'preferredDateTime' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
                'flow_token' => 'nullable|string',
            ]);

            $flowSettings = $this->catalogFlowNodeSettingsService->resolveListingNodeSettings($validated['flow_token'] ?? null);
            if ($flowSettings['requirePreferredDateTime'] && empty($validated['preferredDateTime'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Preferred date and time is required.',
                ], 422);
            }

            $item = $this->findProductInCatalog($catalog->items, $validated['item_id']);
            if (! $item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in catalog.',
                ], 404);
            }

            $details = [
                'customerName' => $validated['customerName'] ?? null,
                'customerPhone' => $validated['customerPhone'],
                'preferredDateTime' => $validated['preferredDateTime'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'completionType' => $flowSettings['completionType'] === 'inquiry' ? 'inquiry' : 'booking',
            ];

            if ($details['completionType'] === 'inquiry') {
                $message = $this->catalogListingInquiryService->buildMessage(
                    $catalog,
                    $item,
                    $details['customerName'],
                    $details['notes']
                );
            } else {
                $message = $this->catalogListingBookingService->buildBookingMessage($catalog, $item, $details);
            }

            $whatsappUrl = $this->catalogListingBookingService->buildWhatsAppUrl(
                $catalog->company,
                $catalog,
                $item,
                $message
            );

            $this->catalogAnalyticsService->record(
                $catalog->company_id,
                $catalog->id,
                'listing_booking',
                ['item_id' => $validated['item_id']]
            );

            $this->catalogBookingPendingService->storePendingFromFlowToken(
                $validated['flow_token'] ?? null,
                $catalog->id,
                $item,
                $details,
                $message
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'whatsapp_url' => $whatsappUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating booking: '.$e->getMessage(),
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
                'customerName' => 'nullable|string|max:255',
                'customerPhone' => 'required|string|max:20',
                'deliveryAddress' => 'required|string|max:1000',
                'amount' => 'required|numeric|min:1',
                'notes' => 'nullable|string',
                'flow_token' => 'nullable|string',
                'visitor_key' => 'nullable|string|max:64',
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
                    'customer_name' => trim((string) ($validated['customerName'] ?? '')) !== ''
                        ? trim((string) $validated['customerName'])
                        : 'Customer',
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

                $orderMessage = '📦 *New Order from Catalog: '.$catalog->name."*\n\n"
                    ."📋 *Items:*\n"
                    .collect($invoiceItems)->map(fn ($item) => "• {$item['title']} (x{$item['quantity']})")
                        ->implode("\n")
                    ."\n\n💰 *Total:* ".$this->catalogCurrencyService->formatAmount($catalog->company, $totalAmount);

                $order = $this->catalogOrderService->createFromCheckout(
                    $catalog,
                    $validated['items'],
                    $catalog->items ?? [],
                    CatalogOrder::CHANNEL_INVOICE,
                    [
                        'customer_name' => $validated['customerName'] ?? null,
                        'customer_phone' => $validated['customerPhone'] ?? null,
                        'delivery_address' => $validated['deliveryAddress'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'flow_token' => $validated['flow_token'] ?? null,
                        'order_message' => $orderMessage,
                    ],
                    $invoice
                );

                $this->catalogCheckoutPendingService->storePendingFromFlowToken(
                    $validated['flow_token'] ?? null,
                    $catalog->id,
                    $validated['items'],
                    $orderMessage
                );

                if (! empty($validated['visitor_key'])) {
                    $this->catalogCartSessionService->markConverted($catalog, (string) $validated['visitor_key']);
                }

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
                    [
                        'item_count' => count($validated['items']),
                        'total' => $totalAmount,
                        'invoice_id' => $invoice->id,
                        'order_id' => $order->id,
                    ]
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
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'public_uuid' => $order->public_uuid,
                        'status' => $order->status,
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating invoice: '.$e->getMessage(),
            ], 400);
        }
    }

    public function syncCart(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        $validated = $request->validate([
            'visitor_key' => 'required|string|max:64',
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'customerPhone' => 'nullable|string|max:40',
            'customerName' => 'nullable|string|max:255',
            'flow_token' => 'nullable|string',
        ]);

        $contactId = null;
        if (! empty($validated['flow_token'])) {
            $context = $this->catalogFlowCallbackService->decodeToken($validated['flow_token']);
            $contactId = $context['contact_id'] ?? null;
        }

        $session = $this->catalogCartSessionService->sync(
            $catalog,
            $validated['visitor_key'],
            $validated['items'] ?? [],
            $validated['customerPhone'] ?? null,
            $validated['customerName'] ?? null,
            $contactId
        );

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'item_count' => $session->itemCount(),
                'last_activity_at' => optional($session->last_activity_at)?->toIso8601String(),
            ],
        ]);
    }

    public function abandonCart(Request $request, $catalogId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        $validated = $request->validate([
            'visitor_key' => 'required|string|max:64',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'customerPhone' => 'required|string|max:40',
            'customerName' => 'nullable|string|max:255',
        ]);

        $session = $this->catalogCartSessionService->markAbandoned(
            $catalog,
            $validated['visitor_key'],
            $validated['items'],
            $validated['customerPhone'],
            $validated['customerName'] ?? null
        );

        $this->catalogAnalyticsService->record(
            $catalog->company_id,
            $catalog->id,
            'cart_abandoned',
            ['item_count' => count($validated['items'])]
        );

        return response()->json([
            'success' => true,
            'abandoned' => (bool) $session,
            'session_id' => $session?->id,
        ]);
    }

    /**
     * Get invoice details (public endpoint for payment page)
     */
    public function getInvoice($invoiceId)
    {
        $invoice = $this->resolvePublicInvoice((string) $invoiceId);

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
        $invoice = $this->resolvePublicInvoice((string) $invoiceId);

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

    /**
     * Booking capability for a catalog listing item (slots vs WhatsApp fallback).
     */
    public function itemBookingConfig(Request $request, $catalogId, string $itemId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog || $catalog->isCommerce()) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        $item = $this->findProductInCatalog($catalog->items, $itemId);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found in catalog.'], 404);
        }

        $config = $this->catalogListingSlotBookingService->bookingConfig(
            $catalog,
            $item,
            $request->query('flow_token')
        );

        return response()->json([
            'success' => true,
            ...$config,
        ]);
    }

    /**
     * Available booking dates for a catalog listing item.
     */
    public function itemAvailabilityDates(Request $request, $catalogId, string $itemId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog || $catalog->isCommerce()) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        $item = $this->findProductInCatalog($catalog->items, $itemId);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found in catalog.'], 404);
        }

        try {
            $dates = $this->catalogListingSlotBookingService->availableDates(
                $catalog,
                $item,
                $request->filled('duration_minutes') ? (int) $request->duration_minutes : null
            );

            return response()->json([
                'success' => true,
                'dates' => $dates,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Available booking slots for a catalog listing item on a given date.
     */
    public function itemAvailabilitySlots(Request $request, $catalogId, string $itemId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog || $catalog->isCommerce()) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        $item = $this->findProductInCatalog($catalog->items, $itemId);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found in catalog.'], 404);
        }

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);

        try {
            $payload = $this->catalogListingSlotBookingService->slotsForDate(
                $catalog,
                $item,
                $validated['date'],
                $validated['duration_minutes'] ?? null
            );

            return response()->json([
                'success' => true,
                ...$payload,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm a catalog listing booking using a selected slot.
     */
    public function bookItem(PublicCatalogBookItemRequest $request, $catalogId, string $itemId)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog || $catalog->isCommerce()) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        $item = $this->findProductInCatalog($catalog->items, $itemId);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found in catalog.'], 404);
        }

        $validated = $request->validated();

        try {
            $result = $this->catalogListingSlotBookingService->bookSlot(
                $catalog,
                $item,
                $validated['slot_id'],
                $validated['customerPhone'],
                $validated['customerName'] ?? null,
                $validated['notes'] ?? null,
                $validated['duration_minutes'] ?? null,
                $validated['flow_token'] ?? null
            );

            $this->catalogAnalyticsService->record(
                $catalog->company_id,
                $catalog->id,
                'listing_booking',
                [
                    'item_id' => $itemId,
                    'reservation_id' => $result['reservation']['id'] ?? null,
                    'requires_payment' => $result['requires_action'],
                ]
            );

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (RuntimeException $exception) {
            $status = $exception->getCode() === 409 ? 409 : 422;

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $status);
        }
    }

    /**
     * Poll M-Pesa payment status for a catalog slot booking.
     */
    public function bookingPaymentStatus(Request $request, $catalogId, string $invoicePublicUuid)
    {
        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);

        if (! $catalog) {
            return response()->json(['success' => false, 'message' => 'Catalog not found'], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => $this->bookingPaymentService->paymentStatus($catalog->company, $invoicePublicUuid),
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
            $publicUrl,
            $catalog->presentation()
        );

        $this->catalogAnalyticsService->record($catalog->company_id, $catalog->id, 'view');

        $currencyCode = $this->catalogCurrencyService->codeForCompany($company);
        $currencySymbol = $this->catalogCurrencyService->symbolForCode($currencyCode);
        $flowToken = $request->query('flow_token');
        $presentation = $catalog->presentation();

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
            'flowNodeSettings' => $this->catalogFlowNodeSettingsService->resolveListingNodeSettings(
                is_string($flowToken) ? $flowToken : null
            ),
            'whatsappOrderNumber' => $this->catalogWhatsAppOrderService->resolveNumber($company),
            'presentation' => $presentation,
            'mapMarkers' => $browse['mapMarkers'],
        ]);
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

    private function resolvePublicInvoice(string $invoiceId): ?Invoice
    {
        if (Str::isUuid($invoiceId)) {
            return Invoice::query()
                ->where('public_uuid', $invoiceId)
                ->first();
        }

        if (ctype_digit($invoiceId)) {
            return Invoice::query()->find((int) $invoiceId);
        }

        return null;
    }
}
