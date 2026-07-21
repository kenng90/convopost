<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaystackCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Invoice\Models\Invoice;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiRailPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'owner']);
    }

    public function test_payment_gateway_manager_lists_paystack_and_mpesa(): void
    {
        $company = Company::factory()->create();
        $manager = app(PaymentGatewayManager::class);
        $available = $manager->availableForCompany($company);

        $keys = collect($available)->pluck('key')->all();
        $this->assertContains('paystack', $keys);
        $this->assertContains('mpesa', $keys);
    }

    public function test_paystack_initialize_creates_pending_payment(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access_test',
                    'reference' => 'ref_test_123',
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $company->setConfig('paystack_commerce_public_key', 'pk_test');
        $company->setConfig('paystack_commerce_secret_key', 'sk_test');

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-TEST-1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254712345678',
            'customer_email' => 'buyer@example.com',
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [],
        ]);

        $result = app(PaystackCommerceService::class)->initiate($company, $invoice);

        $this->assertTrue($result['success']);
        $this->assertSame('https://checkout.paystack.com/test', $result['authorization_url']);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'paystack',
            'gateway_reference' => 'ref_test_123',
            'status' => 'pending',
        ]);
    }

    public function test_paystack_initialize_uses_valid_fallback_email_when_missing(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access_test',
                    'reference' => 'ref_fallback_123',
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $company->setConfig('paystack_commerce_public_key', 'pk_test');
        $company->setConfig('paystack_commerce_secret_key', 'sk_test');

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-TEST-2',
            'customer_name' => 'Buyer',
            'customer_phone' => '254712345678',
            'customer_email' => null,
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [],
        ]);

        $result = app(PaystackCommerceService::class)->initiate($company, $invoice, [
            'email' => null,
        ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && ($request['email'] ?? null) === '254712345678@pay.convoconnect.com';
        });
    }

    public function test_request_payment_node_is_registered_in_flow_factory(): void
    {
        $this->assertTrue(class_exists(\Modules\Flowmaker\Models\Nodes\RequestPayment::class));
        $this->assertTrue(class_exists(\Modules\Flowmaker\Models\Nodes\CatalogSearch::class));
    }
}
