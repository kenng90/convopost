<?php

namespace Tests\Unit;

use Modules\Invoice\Models\Invoice;
use Tests\TestCase;

class InvoicePaymentSourceTest extends TestCase
{
    public function test_flow_invoice_is_identified_by_notes_source(): void
    {
        $invoice = new Invoice([
            'catalog_id' => null,
            'notes' => ['source' => Invoice::SOURCE_FLOW, 'flow_id' => 12],
        ]);

        $this->assertTrue($invoice->isFlowPayment());
        $this->assertSame(Invoice::SOURCE_FLOW, $invoice->getPaymentSource());
        $this->assertSame('Flow STK Push', $invoice->getPaymentSourceLabel());
    }

    public function test_catalog_invoice_is_identified_by_catalog_id(): void
    {
        $invoice = new Invoice([
            'catalog_id' => 5,
            'notes' => null,
        ]);

        $this->assertSame(Invoice::SOURCE_CATALOG, $invoice->getPaymentSource());
        $this->assertSame('Catalog', $invoice->getPaymentSourceLabel());
    }

    public function test_manual_invoice_defaults_to_invoice_source(): void
    {
        $invoice = new Invoice([
            'catalog_id' => null,
            'notes' => null,
        ]);

        $this->assertSame(Invoice::SOURCE_INVOICE, $invoice->getPaymentSource());
        $this->assertSame('Invoice', $invoice->getPaymentSourceLabel());
    }
}
