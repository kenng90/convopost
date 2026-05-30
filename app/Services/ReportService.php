<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class ReportService
{
    protected $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Get transactions report with optional filters
     */
    public function getTransactionsReport(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
        ?string $paymentStatus = null
    ): array {
        $query = InvoicePayment::query()
            ->whereHas('invoice', function ($q) {
                $q->where('company_id', $this->company->id);
            })
            ->with(['invoice'])
            ->orderBy('created_at', 'desc');

        // Apply date filters
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $transactions = $query->get()->map(function ($payment) {
            return $this->formatTransaction($payment);
        });

        // Calculate summary statistics
        $summary = $this->calculateTransactionSummary($transactions);

        return [
            'success' => true,
            'summary' => $summary,
            'transactions' => $transactions->toArray(),
            'total_count' => $transactions->count(),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ],
        ];
    }

    /**
     * Get payments report with invoice details
     */
    public function getPaymentsReport(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $invoiceStatus = null
    ): array {
        $query = Invoice::where('company_id', $this->company->id)
            ->with(['payments', 'catalog'])
            ->orderBy('created_at', 'desc');

        // Apply date filters
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Apply status filter
        if ($invoiceStatus) {
            $query->where('status', $invoiceStatus);
        }

        $invoices = $query->get();

        $payments = $invoices->map(fn ($invoice) => $this->formatInvoiceForPaymentsReport($invoice));

        // Calculate summary
        $summary = $this->calculatePaymentSummary($payments);

        return [
            'success' => true,
            'summary' => $summary,
            'payments' => $payments->toArray(),
            'total_count' => $payments->count(),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'invoice_status' => $invoiceStatus,
            ],
        ];
    }

    /**
     * Format a single invoice row for the payments report (including modal preview data).
     */
    public function formatInvoiceForPaymentsReport(Invoice $invoice): array
    {
        $invoicePayments = $invoice->payments;
        $totalPaid = (float) $invoicePayments->where('status', 'success')->sum('amount');

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'public_uuid' => $invoice->public_uuid,
            'customer_name' => $invoice->customer_name,
            'customer_phone' => $invoice->customer_phone,
            'customer_email' => $invoice->customer_email,
            'currency' => $invoice->currency,
            'items' => $invoice->items ?? [],
            'invoice_amount' => (float) $invoice->amount,
            'total_paid' => $totalPaid,
            'remaining' => max(0, (float) $invoice->amount - $totalPaid),
            'status' => $invoice->status,
            'created_at' => $invoice->created_at->toDateTimeString(),
            'sent_at' => $invoice->sent_at?->toDateTimeString(),
            'paid_at' => $invoice->paid_at?->toDateTimeString(),
            'payment_count' => $invoicePayments->count(),
            'successful_payments' => $invoicePayments->where('status', 'success')->count(),
            'pending_payments' => $invoicePayments->where('status', 'pending')->count(),
            'failed_payments' => $invoicePayments->where('status', 'failed')->count(),
            'payment_records' => $invoicePayments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'mpesa_receipt_number' => $payment->mpesa_receipt_number,
                    'mpesa_checkout_request_id' => $payment->mpesa_checkout_request_id,
                    'created_at' => $payment->created_at->toDateTimeString(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Get reconciliation report
     * Matches local records with M-Pesa responses
     */
    public function getReconciliationReport(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = InvoicePayment::whereHas('invoice', function ($q) {
            $q->where('company_id', $this->company->id);
        })->with(['invoice']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $payments = $query->get();

        $reconciled = [];
        $discrepancies = [];
        $pending = [];

        foreach ($payments as $payment) {
            $record = [
                'payment_id' => $payment->id,
                'invoice_number' => $payment->invoice->invoice_number,
                'customer_phone' => $payment->invoice->customer_phone,
                'checkout_request_id' => $payment->mpesa_checkout_request_id,
                'receipt_number' => $payment->mpesa_receipt_number,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'created_at' => $payment->created_at->toDateTimeString(),
                'updated_at' => $payment->updated_at->toDateTimeString(),
            ];

            if ($payment->status === 'pending') {
                // Pending payments (no M-Pesa callback yet)
                $pending[] = $record;
            } elseif ($payment->status === 'success') {
                // Verify against response data
                if ($this->isReconciled($payment)) {
                    $reconciled[] = array_merge($record, [
                        'reconciliation_status' => 'matched',
                        'mpesa_amount' => $this->getAmountFromResponse($payment),
                    ]);
                } else {
                    $discrepancies[] = array_merge($record, [
                        'reconciliation_status' => 'discrepancy',
                        'issues' => $this->detectIssues($payment),
                    ]);
                }
            } else {
                // Failed payments
                $discrepancies[] = array_merge($record, [
                    'reconciliation_status' => 'failed',
                    'failure_reason' => $this->getFailureReason($payment),
                ]);
            }
        }

        // Calculate summary
        $summary = [
            'total_transactions' => $payments->count(),
            'reconciled' => count($reconciled),
            'with_discrepancies' => count($discrepancies),
            'pending' => count($pending),
            'reconciliation_rate' => $payments->count() > 0 ?
                round((count($reconciled) / $payments->count()) * 100, 2) : 0,
            'total_amount' => (float) $payments->sum('amount'),
            'reconciled_amount' => collect($reconciled)->sum('amount'),
            'discrepancy_amount' => collect($discrepancies)->sum('amount'),
        ];

        return [
            'success' => true,
            'summary' => $summary,
            'reconciled' => $reconciled,
            'discrepancies' => $discrepancies,
            'pending' => $pending,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }

    /**
     * Get daily summary report
     */
    public function getDailySummary(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $start = $startDate ? Carbon::parse($startDate) : now()->subDays(30);
        $end = $endDate ? Carbon::parse($endDate) : now();

        $dailyData = [];

        // Get transactions by day
        $transactions = InvoicePayment::whereHas('invoice', function ($q) {
            $q->where('company_id', $this->company->id);
        })
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(fn ($t) => $t->created_at->format('Y-m-d'));

        // Get invoices by day
        $invoices = Invoice::where('company_id', $this->company->id)
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(fn ($i) => $i->created_at->format('Y-m-d'));

        for ($date = $start; $date <= $end; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $dayTransactions = $transactions->get($dateStr, collect());
            $dayInvoices = $invoices->get($dateStr, collect());

            $dailyData[] = [
                'date' => $dateStr,
                'invoices_created' => $dayInvoices->count(),
                'invoices_amount' => (float) $dayInvoices->sum('amount'),
                'transactions_count' => $dayTransactions->count(),
                'successful' => $dayTransactions->where('status', 'success')->count(),
                'failed' => $dayTransactions->where('status', 'failed')->count(),
                'pending' => $dayTransactions->where('status', 'pending')->count(),
                'total_amount' => (float) $dayTransactions->sum('amount'),
                'successful_amount' => (float) $dayTransactions->where('status', 'success')->sum('amount'),
            ];
        }

        return [
            'success' => true,
            'daily_summary' => $dailyData,
            'date_range' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Format single transaction for report
     */
    private function formatTransaction(InvoicePayment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'invoice_number' => $payment->invoice->invoice_number,
            'invoice_id' => $payment->invoice->id,
            'public_uuid' => $payment->invoice->public_uuid,
            'customer_name' => $payment->invoice->customer_name,
            'customer_phone' => $payment->invoice->customer_phone,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'mpesa_checkout_request_id' => $payment->mpesa_checkout_request_id,
            'mpesa_receipt_number' => $payment->mpesa_receipt_number,
            'created_at' => $payment->created_at->toDateTimeString(),
            'updated_at' => $payment->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Calculate transaction summary statistics
     */
    private function calculateTransactionSummary(Collection $transactions): array
    {
        $statusCounts = $transactions->groupBy('status')->map->count();

        return [
            'total_transactions' => $transactions->count(),
            'successful' => $statusCounts->get('success', 0),
            'failed' => $statusCounts->get('failed', 0),
            'pending' => $statusCounts->get('pending', 0),
            'cancelled' => $statusCounts->get('cancelled', 0),
            'total_amount' => (float) $transactions->sum('amount'),
            'successful_amount' => (float) $transactions->where('status', 'success')->sum('amount'),
            'pending_amount' => (float) $transactions->where('status', 'pending')->sum('amount'),
            'success_rate' => $transactions->count() > 0 ?
                round(($statusCounts->get('success', 0) / $transactions->count()) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate payment summary statistics
     */
    private function calculatePaymentSummary(Collection $payments): array
    {
        $totalInvoices = $payments->count();
        $paidInvoices = $payments->filter(fn ($p) => $p['remaining'] == 0)->count();
        $partiallyPaidInvoices = $payments->filter(fn ($p) => $p['remaining'] > 0 && $p['total_paid'] > 0)->count();
        $unpaidInvoices = $payments->filter(fn ($p) => $p['total_paid'] == 0)->count();

        return [
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'partially_paid' => $partiallyPaidInvoices,
            'unpaid_invoices' => $unpaidInvoices,
            'total_invoice_amount' => (float) $payments->sum('invoice_amount'),
            'total_paid_amount' => (float) $payments->sum('total_paid'),
            'total_pending_amount' => (float) $payments->sum('remaining'),
            'collection_rate' => $payments->sum('invoice_amount') > 0 ?
                round(($payments->sum('total_paid') / $payments->sum('invoice_amount')) * 100, 2) : 0,
        ];
    }

    /**
     * Check if payment is reconciled
     */
    private function isReconciled(InvoicePayment $payment): bool
    {
        if (! $payment->response_data) {
            return false;
        }

        $response = $payment->response_data;

        // Check if amount matches
        if (isset($response['Body']['stkCallback']['CallbackMetadata']['Item'])) {
            foreach ($response['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
                if ($item['Name'] === 'Amount' && $item['Value'] == $payment->amount) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get amount from M-Pesa response
     */
    private function getAmountFromResponse(InvoicePayment $payment): ?float
    {
        if (! $payment->response_data) {
            return null;
        }

        $response = $payment->response_data;

        if (isset($response['Body']['stkCallback']['CallbackMetadata']['Item'])) {
            foreach ($response['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
                if ($item['Name'] === 'Amount') {
                    return (float) $item['Value'];
                }
            }
        }

        return null;
    }

    /**
     * Detect issues in payment reconciliation
     */
    private function detectIssues(InvoicePayment $payment): array
    {
        $issues = [];

        if (! $payment->mpesa_receipt_number) {
            $issues[] = 'Missing M-Pesa receipt number';
        }

        if (! $payment->response_data) {
            $issues[] = 'Missing response data';
        }

        $mpesaAmount = $this->getAmountFromResponse($payment);
        if ($mpesaAmount && $mpesaAmount != $payment->amount) {
            $issues[] = "Amount mismatch: expected {$payment->amount}, got {$mpesaAmount}";
        }

        return $issues;
    }

    /**
     * Get failure reason from response
     */
    private function getFailureReason(InvoicePayment $payment): string
    {
        if (! $payment->response_data) {
            return 'No response data';
        }

        $response = $payment->response_data;

        if (isset($response['Body']['stkCallback']['ResultDesc'])) {
            return $response['Body']['stkCallback']['ResultDesc'];
        }

        return 'Unknown failure';
    }
}
