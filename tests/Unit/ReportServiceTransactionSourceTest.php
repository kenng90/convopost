<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Tests\TestCase;

class ReportServiceTransactionSourceTest extends TestCase
{
    public function test_format_transaction_includes_payment_source(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'FLW-20260101-1',
            'customer_name' => 'Jane Doe',
            'customer_phone' => '254712345678',
            'catalog_id' => null,
            'notes' => ['source' => Invoice::SOURCE_FLOW],
        ]);
        $invoice->id = 1;
        $invoice->public_uuid = 'uuid-123';

        $payment = new InvoicePayment([
            'amount' => 500.00,
            'status' => 'success',
            'mpesa_receipt_number' => 'QAB123CDE',
            'mpesa_checkout_request_id' => 'ws_CO_123',
        ]);
        $payment->id = 10;
        $payment->created_at = Carbon::parse('2026-01-15 11:00:00');
        $payment->updated_at = Carbon::parse('2026-01-15 11:01:00');
        $payment->setRelation('invoice', $invoice);

        $company = new Company(['name' => 'Test Co']);
        $company->id = 1;

        $formatted = (new ReportService($company))->formatTransaction($payment);

        $this->assertSame('flow', $formatted['source']);
        $this->assertSame('Flow STK Push', $formatted['source_label']);
        $this->assertSame('FLW-20260101-1', $formatted['invoice_number']);
    }
}
