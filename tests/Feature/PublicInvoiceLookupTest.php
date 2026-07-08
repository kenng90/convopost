<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Invoice\Models\Invoice;
use Tests\TestCase;

class PublicInvoiceLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_page_resolves_invoice_by_public_uuid_only(): void
    {
        $company = Company::factory()->create();

        $targetUuid = '6d916216-e622-4bd4-b55f-9bbca626b329';

        Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => '+15-20260707-10',
            'customer_name' => 'Channeth',
            'customer_phone' => '254700000001',
            'amount' => 0.50,
            'currency' => 'KES',
            'status' => 'sent',
            'description' => 'Deep Tissue',
            'public_uuid' => $targetUuid,
            'items' => [
                ['title' => 'Deep Tissue', 'quantity' => 1, 'price' => 0.50, 'total' => 0.50],
            ],
            'sent_at' => now(),
        ]);

        Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => '+15-20260627-6',
            'customer_name' => 'Kenneth',
            'customer_phone' => '254716217015',
            'amount' => 8000,
            'currency' => 'KES',
            'status' => 'sent',
            'description' => 'Garden Landscaping Visit',
            'public_uuid' => (string) Str::uuid(),
            'items' => [
                ['title' => 'Garden Landscaping Visit', 'quantity' => 1, 'price' => 8000, 'total' => 8000],
            ],
            'sent_at' => now(),
        ]);

        $response = $this->get(route('catalog.invoice.pay', $targetUuid));

        $response->assertOk();
        $response->assertSee('Deep Tissue', false);
        $response->assertSee('0.50', false);
        $response->assertDontSee('Garden Landscaping Visit', false);
        $response->assertDontSee('8,000.00', false);
    }

    public function test_invoice_api_resolves_numeric_id_when_not_uuid(): void
    {
        $company = Company::factory()->create();

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-100',
            'customer_name' => 'Numeric Lookup',
            'customer_phone' => '254700000002',
            'amount' => 12.00,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [['title' => 'Consult', 'quantity' => 1, 'price' => 12, 'total' => 12]],
            'sent_at' => now(),
        ]);

        $response = $this->getJson(route('catalog.invoice', $invoice->id));

        $response->assertOk();
        $response->assertJsonPath('invoice.invoice_number', 'INV-100');
        $response->assertJsonPath('invoice.amount', 12);
    }
}
