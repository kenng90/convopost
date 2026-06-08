<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicCatalogBrowseRequest;
use App\Models\ListCatalog;
use App\Services\CatalogItemFilterService;
use App\Services\InvoiceWhatsAppService;
use Illuminate\Http\Request;
use Modules\Invoice\Models\Invoice;

class PublicCatalogController extends Controller
{
    public function __construct(
        protected CatalogItemFilterService $catalogItemFilter,
    ) {
    }

    /**
     * Display public catalog page
     */
    public function show(PublicCatalogBrowseRequest $request, $catalogId)
    {
        $catalog = ListCatalog::find($catalogId);

        if (! $catalog) {
            return view('public.catalog.not-found', [
                'message' => 'Catalog not found',
            ]);
        }

        $company = $catalog->company;
        $browse = $this->catalogItemFilter->browse(
            $catalog->items ?? [],
            $request->filters(),
            route('catalog.public', ['catalogId' => $catalog->id])
        );

        return view('public.catalog.index', [
            'catalog' => $catalog,
            'company' => $company,
            'items' => $browse['items'],
            'filterOptions' => $browse['filterOptions'],
            'filters' => $browse['filters'],
            'totalInCatalog' => $browse['totalInCatalog'],
            'filteredTotal' => $browse['filteredTotal'],
        ]);
    }

    /**
     * API endpoint to get catalog data (supports search, filters, pagination)
     */
    public function getItems(PublicCatalogBrowseRequest $request, $catalogId)
    {
        $catalog = ListCatalog::find($catalogId);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }

        $browse = $this->catalogItemFilter->browse(
            $catalog->items ?? [],
            $request->filters(),
            route('catalog.items', ['catalogId' => $catalog->id])
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

    /**
     * Generate WhatsApp order message
     */
    public function generateOrder(Request $request, $catalogId)
    {
        $catalog = ListCatalog::find($catalogId);

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
            ]);

            // Build order message
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
                        $orderMessage .= "Price: {$product['price']} = ".$itemTotal."\n";
                        $totalPrice += $itemTotal;
                    } else {
                        $orderMessage .= "\n";
                    }
                }
            }

            if ($totalPrice > 0) {
                $orderMessage .= "\n💰 *Total:* ".$totalPrice."\n";
            }

            if ($validated['notes'] ?? null) {
                $orderMessage .= "\n📝 *Notes:* ".$validated['notes']."\n";
            }

            return response()->json([
                'success' => true,
                'message' => $orderMessage,
                'orderData' => $validated,
            ]);

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
        $catalog = ListCatalog::find($catalogId);

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
                'customerEmail' => 'nullable|email',
                'amount' => 'required|numeric|min:1',
                'notes' => 'nullable|string',
            ]);

            // Calculate total from items
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

            // Verify amount matches
            if (abs($totalAmount - floatval($validated['amount'])) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount mismatch. Please refresh and try again.',
                ], 400);
            }

            // Create invoice
            $invoice = Invoice::create([
                'company_id' => $catalog->company_id,
                'catalog_id' => $catalog->id,
                'invoice_number' => Invoice::generateInvoiceNumber($catalog->company),
                'customer_name' => $validated['customerName'] ?? 'Guest Customer',
                'customer_phone' => $validated['customerPhone'],
                'customer_email' => $validated['customerEmail'] ?? null,
                'amount' => $totalAmount,
                'currency' => 'KES',
                'status' => 'draft',
                'description' => $validated['notes'] ?? null,
                'items' => $invoiceItems,
            ]);

            // Send invoice via WhatsApp
            $whatsAppService = new InvoiceWhatsAppService($catalog->company);
            $whatsAppSent = $whatsAppService->sendInvoice($invoice);

            // Update invoice status to 'sent' if WhatsApp message sent successfully
            if ($whatsAppSent) {
                $invoice->markAsSent();
            }

            // Refresh invoice to get latest data including public_uuid
            $invoice->refresh();

            // Use UUID if available, fallback to ID
            $invoiceIdentifier = $invoice->public_uuid ?? $invoice->id;

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
        // Try to find by UUID first (new way), then by ID (backward compatibility)
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
                'id' => $invoice->public_uuid ?? $invoice->id, // Use UUID if available
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
        // Try to find by UUID first (new way), then by ID (backward compatibility during migration)
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
                'id' => $invoice->public_uuid ?? $invoice->id, // Use UUID if available, fallback to ID
                'public_uuid' => $invoice->public_uuid, // Include UUID explicitly for view
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
     * Find product in catalog items by ID
     */
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
}
