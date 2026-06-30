<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Tests\TestCase;

class ReportServicePaymentsReportTest extends TestCase
{
    public function test_format_invoice_for_payments_report_includes_items_and_payment_records(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'TST-20260101-1',
            'customer_name' => 'Jane Doe',
            'customer_phone' => '254712345678',
            'customer_email' => 'jane@example.com',
            'delivery_address' => '45 Kenyatta Avenue, Nairobi',
            'amount' => 1500.00,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [
                [
                    'title' => 'Consulting',
                    'quantity' => 2,
                    'total' => 1500.00,
                ],
            ],
        ]);
        $invoice->id = 1;
        $invoice->created_at = Carbon::parse('2026-01-15 10:00:00');

        $payment = new InvoicePayment([
            'amount' => 500.00,
            'status' => 'success',
            'mpesa_receipt_number' => 'QAB123CDE',
            'mpesa_checkout_request_id' => 'ws_CO_123',
        ]);
        $payment->id = 10;
        $payment->created_at = Carbon::parse('2026-01-15 11:00:00');

        $invoice->setRelation('payments', collect([$payment]));

        $company = new Company(['name' => 'Test Co']);
        $company->id = 1;

        $formatted = (new ReportService($company))->formatInvoiceForPaymentsReport($invoice);

        $this->assertSame('TST-20260101-1', $formatted['invoice_number']);
        $this->assertSame('45 Kenyatta Avenue, Nairobi', $formatted['delivery_address']);
        $this->assertSame('KES', $formatted['currency']);
        $this->assertCount(1, $formatted['items']);
        $this->assertSame('Consulting', $formatted['items'][0]['title']);
        $this->assertSame(500.0, $formatted['total_paid']);
        $this->assertSame(1000.0, $formatted['remaining']);
        $this->assertCount(1, $formatted['payment_records']);
        $this->assertSame('success', $formatted['payment_records'][0]['status']);
        $this->assertSame('QAB123CDE', $formatted['payment_records'][0]['mpesa_receipt_number']);
    }
}
