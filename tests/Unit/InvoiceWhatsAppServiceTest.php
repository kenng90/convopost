<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\InvoiceWhatsAppService;
use Modules\Invoice\Models\Invoice;
use Tests\TestCase;

class InvoiceWhatsAppServiceTest extends TestCase
{
    public function test_build_template_components_maps_invoice_fields(): void
    {
        $company = new Company(['id' => 1, 'name' => 'Acme Shop']);
        $invoice = new Invoice([
            'invoice_number' => 'INV-99',
            'customer_name' => 'Jane',
            'amount' => 1500,
            'public_uuid' => 'uuid-abc',
            'items' => [
                ['title' => 'Shoes', 'quantity' => 2, 'price' => 500, 'total' => 1000],
                ['title' => 'Bag', 'quantity' => 1, 'price' => 500, 'total' => 500],
            ],
        ]);

        $service = new InvoiceWhatsAppService($company);
        $method = new \ReflectionMethod(InvoiceWhatsAppService::class, 'buildTemplateComponents');
        $method->setAccessible(true);
        $components = $method->invoke($service, $invoice);

        $this->assertSame('body', $components[0]['type']);
        $this->assertSame('Jane', $components[0]['parameters'][0]['text']);
        $this->assertSame('INV-99', $components[0]['parameters'][1]['text']);
        $this->assertStringContainsString('Shoes', $components[0]['parameters'][2]['text']);
        $this->assertSame('KES 1,500.00', $components[0]['parameters'][3]['text']);
        $this->assertStringContainsString('/catalog/pay/uuid-abc', $components[0]['parameters'][4]['text']);
    }

    public function test_build_items_summary_truncates_long_orders(): void
    {
        $company = new Company(['id' => 1, 'name' => 'Acme']);
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = ['title' => "Item {$i}", 'quantity' => 1, 'price' => 10, 'total' => 10];
        }

        $invoice = new Invoice(['items' => $items]);
        $service = new InvoiceWhatsAppService($company);
        $method = new \ReflectionMethod(InvoiceWhatsAppService::class, 'buildItemsSummary');
        $method->setAccessible(true);
        $summary = $method->invoke($service, $invoice);

        $this->assertStringContainsString('Item 1', $summary);
        $this->assertStringContainsString('+2 more item(s)', $summary);
    }
}
