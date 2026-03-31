<?php

namespace Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\InvoiceWhatsAppService;
use App\Services\MpesaService;
use App\Services\MpesaCallbackValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class InvoiceController extends Controller
{
    /**
     * Create invoice from catalog order
     */
    public function createFromOrder(Request $request)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'catalog_id' => 'required|exists:list_catalogs,id',
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'required|string|max:20',
                'customer_email' => 'nullable|email',
                'items' => 'required|array',
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string',
            ]);

            $companyId = auth()->user()->company_id;
            $company = Company::find($companyId);
            $catalog = ListCatalog::where('id', $validated['catalog_id'])
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Generate unique invoice number
            $invoiceNumber = Invoice::generateInvoiceNumber($company);

            // Create invoice
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'catalog_id' => $validated['catalog_id'],
                'invoice_number' => $invoiceNumber,
                'customer_name' => $validated['customer_name'] ?? 'Guest',
                'customer_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'],
                'amount' => $validated['amount'],
                'currency' => 'KES',
                'status' => 'draft',
                'description' => $validated['description'],
                'items' => $validated['items'],
            ]);

            Log::info('Invoice created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'company_id' => $companyId,
            ]);

            // Send invoice via WhatsApp
            $whatsAppService = new InvoiceWhatsAppService($company);
            $whatsAppSent = $whatsAppService->sendInvoice($invoice);

            // Update invoice status to 'sent' if WhatsApp message sent successfully
            if ($whatsAppSent) {
                $invoice->markAsSent();
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully' . ($whatsAppSent ? ' and sent via WhatsApp' : ''),
                'invoice' => $invoice->toInvoiceArray(),
                'whatsapp_sent' => $whatsAppSent,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create invoice', [
                'error' => $e->getMessage(),
                'company_id' => auth()->user()->company_id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get invoice by ID
     */
    public function show(Invoice $invoice)
    {
        if (auth()->check() && auth()->user()->company_id !== $invoice->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'invoice' => $invoice->toInvoiceArray(),
            'payments' => $invoice->payments()->get(),
        ]);
    }

    /**
     * Initiate M-Pesa payment for invoice
     */
    public function initiatePayment(Request $request, Invoice $invoice)
    {
        try {
            $validated = $request->validate([
                'amount' => 'nullable|numeric|min:1',
                'customer_phone' => 'required|string|max:20', // Customer must provide phone for verification
            ]);

            // Authorization check: Verify customer phone matches invoice
            if ($validated['customer_phone'] !== $invoice->customer_phone) {
                Log::warning('Unauthorized payment attempt - phone mismatch', [
                    'invoice_id' => $invoice->id,
                    'provided_phone' => $validated['customer_phone'],
                    'expected_phone' => $invoice->customer_phone,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer phone number',
                ], 403);
            }

            $amount = $validated['amount'] ?? $invoice->getRemainingAmount();

            if ($amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment amount',
                ], 400);
            }

            if ($amount > $invoice->getRemainingAmount()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance',
                ], 400);
            }

            // Initialize M-Pesa service
            $mpesaService = new MpesaService($invoice->company);

            if (!$mpesaService->isConfigured()) {
                $errors = $mpesaService->getConfigErrors();
                Log::error('M-Pesa not configured for invoice payment', [
                    'invoice_id' => $invoice->id,
                    'errors' => $errors,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'M-Pesa is not configured. Please contact support.',
                    'errors' => $errors,
                ], 400);
            }

            // Create payment record
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_method' => 'mpesa',
                'amount' => $amount,
                'status' => 'pending',
            ]);

            // Initiate STK Push
            $result = $mpesaService->initiateStk(
                phone: $invoice->customer_phone,
                amount: $amount,
                accountReference: "INV-{$invoice->invoice_number}",
                transactionDesc: "Invoice {$invoice->invoice_number}",
                callbackUrl: config('app.url') . '/api/invoice/payment/callback'
            );

            if (!$result['success']) {
                $payment->markAsFailed($result['error']);
                Log::error('Failed to initiate M-Pesa STK Push', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'error' => $result['error'],
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], 400);
            }

            // Update payment with M-Pesa request IDs
            $payment->update([
                'mpesa_checkout_request_id' => $result['checkout_request_id'],
                'mpesa_merchant_request_id' => $result['merchant_request_id'],
                'initiated_at' => now(),
            ]);

            Log::info('M-Pesa STK Push initiated for invoice', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'checkout_request_id' => $result['checkout_request_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated. Please enter your M-Pesa PIN.',
                'payment_id' => $payment->id,
                'checkout_request_id' => $result['checkout_request_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Exception during payment initiation', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle M-Pesa payment callback with validation
     */
    public function handleCallback(Request $request)
    {
        try {
            $body = $request->input('Body.stkCallback') ?? $request->input('Body') ?? [];

            // Log callback received
            MpesaCallbackValidator::logCallback($body, 'received');

            // Validate callback structure and required fields
            $validation = MpesaCallbackValidator::validateStkPushCallback($body);
            if (!$validation['valid']) {
                Log::error('Invalid M-Pesa callback structure', [
                    'errors' => $validation['errors'],
                    'body' => $body,
                ]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
            if (!$checkoutRequestId) {
                Log::warning('Invalid callback: missing CheckoutRequestID');
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Check for duplicate callbacks (replay attack prevention)
            if (!MpesaCallbackValidator::isNotDuplicate($checkoutRequestId)) {
                Log::warning('Duplicate M-Pesa callback - already processed', [
                    'checkout_request_id' => $checkoutRequestId,
                ]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Find payment by checkout request ID
            $payment = InvoicePayment::findByCheckoutRequestId($checkoutRequestId);
            if (!$payment) {
                Log::warning('Payment not found for checkout request', [
                    'checkout_request_id' => $checkoutRequestId,
                ]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Initialize M-Pesa service to parse callback
            $mpesaService = new MpesaService(Company::find($payment->invoice->company_id));
            $callbackData = $mpesaService->parseCallback($body);

            // Store full response data for audit trail
            $payment->storeResponseData($body);

            // Update payment status based on result code
            if ($callbackData['success']) {
                $payment->markAsSuccess($callbackData['receipt_number']);
                Log::info('Invoice payment successful - callback validated', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'receipt' => $callbackData['receipt_number'],
                    'checkout_request_id' => $checkoutRequestId,
                ]);
            } else {
                $payment->markAsFailed($callbackData['result_description']);
                Log::warning('Invoice payment failed - callback validated', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'result_code' => $callbackData['result_code'],
                    'result_description' => $callbackData['result_description'],
                    'checkout_request_id' => $checkoutRequestId,
                ]);
            }

            // Log successful processing
            MpesaCallbackValidator::logCallback($body, 'processed');

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\Exception $e) {
            Log::error('Exception handling payment callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Always return accepted to prevent Safaricom retries
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }
    }

    /**
     * Check payment status
     */
    public function checkPaymentStatus(Request $request, Invoice $invoice, InvoicePayment $payment)
    {
        // Authorization check: Verify customer phone
        $customerPhone = $request->query('customer_phone') ?? $request->input('customer_phone');
        if (!$customerPhone || $customerPhone !== $invoice->customer_phone) {
            Log::warning('Unauthorized payment status check - phone mismatch', [
                'invoice_id' => $invoice->id,
                'provided_phone' => $customerPhone,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($payment->invoice_id !== $invoice->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found for this invoice',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'receipt' => $payment->mpesa_receipt_number,
                'completed_at' => $payment->completed_at,
            ],
            'invoice' => [
                'status' => $invoice->status,
                'total_paid' => $invoice->getTotalPaidAmount(),
                'remaining' => $invoice->getRemainingAmount(),
            ],
        ]);
    }

    /**
     * List invoices for authenticated user
     */
    public function listInvoices(Request $request)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $companyId = auth()->user()->company_id;

        $invoices = Invoice::where('company_id', $companyId)
            ->with('payments')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'invoices' => $invoices->items(),
            'pagination' => [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }
}
