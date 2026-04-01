<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    protected $reportService;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get selected company with authorization check
     */
    protected function getSelectedCompany(Request $request)
    {
        $user = auth()->user();
        $accessibleCompanies = $user->accessibleCompanies();

        $companyId = $request->input('company_id', $user->company->id);
        $company = $accessibleCompanies->where('id', $companyId)->first();

        // Fallback to current company if not found or unauthorized
        if (!$company) {
            $company = $user->company;
        }

        return $company;
    }

    /**
     * Show reports dashboard
     */
    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $accessibleCompanies = $user->accessibleCompanies();

        // Get selected company from request or default to current company
        $companyId = $request->input('company_id', $user->company->id);
        $company = $accessibleCompanies->where('id', $companyId)->first();

        // If user doesn't have access to selected company, use current company
        if (!$company) {
            $company = $user->company;
        }

        return view('reports.dashboard', [
            'company' => $company,
            'accessibleCompanies' => $accessibleCompanies,
            'currentCompanyId' => $company->id,
        ]);
    }

    /**
     * Get transactions report
     */
    public function transactions(Request $request)
    {
        $company = $this->getSelectedCompany($request);
        $accessibleCompanies = auth()->user()->accessibleCompanies();
        $reportService = new ReportService($company);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|in:pending,success,failed,cancelled',
            'format' => 'nullable|in:json,csv',
        ]);

        $report = $reportService->getTransactionsReport(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['status'] ?? null
        );

        if (($validated['format'] ?? null) === 'csv') {
            return $this->exportTransactionsCsv($report);
        }

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.transactions', [
            'report' => $report,
            'company' => $company,
            'accessibleCompanies' => $accessibleCompanies,
            'currentCompanyId' => $company->id,
            'filters' => [
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    /**
     * Get payments report
     */
    public function payments(Request $request)
    {
        $company = $this->getSelectedCompany($request);
        $accessibleCompanies = auth()->user()->accessibleCompanies();
        $reportService = new ReportService($company);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'invoice_status' => 'nullable|in:draft,sent,paid,cancelled',
            'format' => 'nullable|in:json,csv',
        ]);

        $report = $reportService->getPaymentsReport(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['invoice_status'] ?? null
        );

        if (($validated['format'] ?? null) === 'csv') {
            return $this->exportPaymentsCsv($report);
        }

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.payments', [
            'report' => $report,
            'company' => $company,
            'accessibleCompanies' => $accessibleCompanies,
            'currentCompanyId' => $company->id,
            'filters' => [
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
                'invoice_status' => $validated['invoice_status'] ?? '',
            ],
        ]);
    }

    /**
     * Get reconciliation report
     */
    public function reconciliation(Request $request)
    {
        $company = $this->getSelectedCompany($request);
        $accessibleCompanies = auth()->user()->accessibleCompanies();
        $reportService = new ReportService($company);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:json,csv',
        ]);

        $report = $reportService->getReconciliationReport(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        if (($validated['format'] ?? null) === 'csv') {
            return $this->exportReconciliationCsv($report);
        }

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.reconciliation', [
            'report' => $report,
            'company' => $company,
            'accessibleCompanies' => $accessibleCompanies,
            'currentCompanyId' => $company->id,
            'filters' => [
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
            ],
        ]);
    }

    /**
     * Get daily summary report
     */
    public function dailySummary(Request $request)
    {
        $company = $this->getSelectedCompany($request);
        $accessibleCompanies = auth()->user()->accessibleCompanies();
        $reportService = new ReportService($company);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:json,csv',
        ]);

        $report = $reportService->getDailySummary(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        if (($validated['format'] ?? null) === 'csv') {
            return $this->exportDailySummaryCsv($report);
        }

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.daily-summary', [
            'report' => $report,
            'company' => $company,
            'accessibleCompanies' => $accessibleCompanies,
            'currentCompanyId' => $company->id,
            'filters' => [
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
            ],
        ]);
    }

    /**
     * Export transactions to CSV
     */
    private function exportTransactionsCsv(array $report)
    {
        $filename = 'transactions-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($report) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, [
                'Payment ID',
                'Invoice Number',
                'Customer Name',
                'Customer Phone',
                'Amount',
                'Status',
                'M-Pesa Checkout ID',
                'M-Pesa Receipt Number',
                'Created Date',
                'Updated Date',
            ]);

            // Write data
            foreach ($report['transactions'] as $transaction) {
                fputcsv($file, [
                    $transaction['payment_id'],
                    $transaction['invoice_number'],
                    $transaction['customer_name'],
                    $transaction['customer_phone'],
                    $transaction['amount'],
                    $transaction['status'],
                    $transaction['mpesa_checkout_request_id'],
                    $transaction['mpesa_receipt_number'] ?? '',
                    $transaction['created_at'],
                    $transaction['updated_at'],
                ]);
            }

            // Write summary
            fputcsv($file, []);
            fputcsv($file, ['SUMMARY']);
            fputcsv($file, ['Total Transactions', $report['summary']['total_transactions']]);
            fputcsv($file, ['Successful', $report['summary']['successful']]);
            fputcsv($file, ['Failed', $report['summary']['failed']]);
            fputcsv($file, ['Pending', $report['summary']['pending']]);
            fputcsv($file, ['Total Amount', $report['summary']['total_amount']]);
            fputcsv($file, ['Success Rate', $report['summary']['success_rate'] . '%']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export payments to CSV
     */
    private function exportPaymentsCsv(array $report)
    {
        $filename = 'payments-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($report) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, [
                'Invoice Number',
                'Customer Name',
                'Customer Phone',
                'Invoice Amount',
                'Total Paid',
                'Remaining',
                'Status',
                'Payment Count',
                'Successful Payments',
                'Pending Payments',
                'Failed Payments',
                'Created Date',
                'Paid Date',
            ]);

            // Write data
            foreach ($report['payments'] as $payment) {
                fputcsv($file, [
                    $payment['invoice_number'],
                    $payment['customer_name'],
                    $payment['customer_phone'],
                    $payment['invoice_amount'],
                    $payment['total_paid'],
                    $payment['remaining'],
                    $payment['status'],
                    $payment['payment_count'],
                    $payment['successful_payments'],
                    $payment['pending_payments'],
                    $payment['failed_payments'],
                    $payment['created_at'],
                    $payment['paid_at'] ?? '',
                ]);
            }

            // Write summary
            fputcsv($file, []);
            fputcsv($file, ['SUMMARY']);
            fputcsv($file, ['Total Invoices', $report['summary']['total_invoices']]);
            fputcsv($file, ['Paid Invoices', $report['summary']['paid_invoices']]);
            fputcsv($file, ['Partially Paid', $report['summary']['partially_paid']]);
            fputcsv($file, ['Unpaid Invoices', $report['summary']['unpaid_invoices']]);
            fputcsv($file, ['Total Amount', $report['summary']['total_invoice_amount']]);
            fputcsv($file, ['Total Paid', $report['summary']['total_paid_amount']]);
            fputcsv($file, ['Total Pending', $report['summary']['total_pending_amount']]);
            fputcsv($file, ['Collection Rate', $report['summary']['collection_rate'] . '%']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export reconciliation to CSV
     */
    private function exportReconciliationCsv(array $report)
    {
        $filename = 'reconciliation-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($report) {
            $file = fopen('php://output', 'w');

            // Write summary
            fputcsv($file, ['RECONCILIATION SUMMARY']);
            fputcsv($file, ['Total Transactions', $report['summary']['total_transactions']]);
            fputcsv($file, ['Reconciled', $report['summary']['reconciled']]);
            fputcsv($file, ['With Discrepancies', $report['summary']['with_discrepancies']]);
            fputcsv($file, ['Pending', $report['summary']['pending']]);
            fputcsv($file, ['Reconciliation Rate', $report['summary']['reconciliation_rate'] . '%']);
            fputcsv($file, []);

            // Write reconciled transactions
            fputcsv($file, ['RECONCILED TRANSACTIONS']);
            fputcsv($file, [
                'Payment ID',
                'Invoice Number',
                'Amount',
                'Receipt Number',
                'Status',
                'Created Date',
            ]);

            foreach ($report['reconciled'] as $tx) {
                fputcsv($file, [
                    $tx['payment_id'],
                    $tx['invoice_number'],
                    $tx['amount'],
                    $tx['receipt_number'] ?? '',
                    $tx['reconciliation_status'],
                    $tx['created_at'],
                ]);
            }

            fputcsv($file, []);

            // Write discrepancies
            fputcsv($file, ['TRANSACTIONS WITH DISCREPANCIES']);
            fputcsv($file, [
                'Payment ID',
                'Invoice Number',
                'Amount',
                'Status',
                'Issues',
                'Created Date',
            ]);

            foreach ($report['discrepancies'] as $tx) {
                fputcsv($file, [
                    $tx['payment_id'],
                    $tx['invoice_number'],
                    $tx['amount'],
                    $tx['status'],
                    implode('; ', $tx['issues'] ?? []),
                    $tx['created_at'],
                ]);
            }

            fputcsv($file, []);

            // Write pending transactions
            fputcsv($file, ['PENDING TRANSACTIONS']);
            fputcsv($file, [
                'Payment ID',
                'Invoice Number',
                'Amount',
                'Status',
                'Created Date',
            ]);

            foreach ($report['pending'] as $tx) {
                fputcsv($file, [
                    $tx['payment_id'],
                    $tx['invoice_number'],
                    $tx['amount'],
                    $tx['status'],
                    $tx['created_at'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export daily summary to CSV
     */
    private function exportDailySummaryCsv(array $report)
    {
        $filename = 'daily-summary-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($report) {
            $file = fopen('php://output', 'w');

            // Write headers
            fputcsv($file, [
                'Date',
                'Invoices Created',
                'Invoices Amount',
                'Transactions Count',
                'Successful',
                'Failed',
                'Pending',
                'Total Transaction Amount',
                'Successful Amount',
            ]);

            // Write data
            foreach ($report['daily_summary'] as $day) {
                fputcsv($file, [
                    $day['date'],
                    $day['invoices_created'],
                    $day['invoices_amount'],
                    $day['transactions_count'],
                    $day['successful'],
                    $day['failed'],
                    $day['pending'],
                    $day['total_amount'],
                    $day['successful_amount'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
