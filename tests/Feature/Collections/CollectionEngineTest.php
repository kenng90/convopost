<?php

namespace Tests\Feature\Collections;

use App\Contracts\PaymentGateway;
use App\Enums\CollectionStatus;
use App\Jobs\Collections\WatchStkTimeout;
use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\Collections\CollectionEngine;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'owner']);

        $plan = Plans::create([
            'name' => 'Pro',
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);

        $this->owner = User::factory()->create(['plan_id' => $plan->id]);
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
    }

    public function test_start_initiates_stk_and_watches_timeout(): void
    {
        Queue::fake();
        $this->bindMpesaGateway();

        $invoice = $this->makeInvoice();
        $result = app(CollectionEngine::class)->start($invoice);

        $this->assertTrue($result['success']);
        $this->assertSame(CollectionStatus::PendingPin->value, $invoice->fresh()->collection_status);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'status' => 'pending',
        ]);
        Queue::assertPushed(WatchStkTimeout::class);
    }

    public function test_successful_stk_marks_invoice_paid(): void
    {
        $invoice = $this->makeInvoice();
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'paid_via' => 'mpesa',
            'amount' => 500,
            'status' => 'pending',
            'mpesa_checkout_request_id' => 'ws_CO_ok',
        ]);

        $payment->markAsSuccess('QAB123XYZ');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(CollectionStatus::Paid->value, $invoice->fresh()->collection_status);
        $this->assertSame('success', $payment->fresh()->status);
    }

    private function makeInvoice(): Invoice
    {
        return Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-COL-'.uniqid(),
            'customer_name' => 'Buyer',
            'customer_phone' => '254712345678',
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [],
        ]);
    }

    private function bindMpesaGateway(): void
    {
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('key')->andReturn('mpesa');
        $gateway->shouldReceive('isConfigured')->andReturn(true);
        $gateway->shouldReceive('initiate')->andReturnUsing(function ($company, Invoice $invoice) {
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_method' => 'mpesa',
                'paid_via' => 'mpesa',
                'amount' => $invoice->amount,
                'status' => 'pending',
                'mpesa_checkout_request_id' => 'ws_CO_test',
                'initiated_at' => now(),
            ]);

            return ['success' => true, 'payment' => $payment, 'message' => 'STK sent'];
        });

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('preferredForCompany')->andReturn($gateway);
        $manager->shouldReceive('get')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);
    }
}
