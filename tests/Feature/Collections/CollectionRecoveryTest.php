<?php

namespace Tests\Feature\Collections;

use App\Contracts\PaymentGateway;
use App\Enums\CollectionStatus;
use App\Jobs\Collections\RequestCollectionPayment;
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

class CollectionRecoveryTest extends TestCase
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

    public function test_failed_stk_schedules_retry(): void
    {
        Queue::fake();

        $invoice = $this->makeInvoice();
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'paid_via' => 'mpesa',
            'amount' => 500,
            'status' => 'pending',
            'mpesa_checkout_request_id' => 'ws_CO_fail',
        ]);

        $payment->markAsFailed('User cancelled');

        $this->assertSame(CollectionStatus::Failed->value, $invoice->fresh()->collection_status);
        Queue::assertPushed(RequestCollectionPayment::class, function ($job) use ($invoice) {
            return $job->invoiceId === $invoice->id && $job->channel === 'mpesa';
        });
    }

    public function test_second_stk_failure_falls_back_to_paystack(): void
    {
        Queue::fake();
        $this->bindPaystackGateway();

        $invoice = $this->makeInvoice();
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'paid_via' => 'mpesa',
            'amount' => 500,
            'status' => 'failed',
            'mpesa_checkout_request_id' => 'ws_CO_1',
        ]);
        $second = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'paid_via' => 'mpesa',
            'amount' => 500,
            'status' => 'pending',
            'mpesa_checkout_request_id' => 'ws_CO_2',
        ]);

        $second->markAsFailed('Timeout');

        $this->assertSame(CollectionStatus::Chasing->value, $invoice->fresh()->collection_status);
        Queue::assertPushed(RequestCollectionPayment::class, function ($job) use ($invoice) {
            return $job->invoiceId === $invoice->id && $job->channel === 'paystack';
        });
    }

    public function test_stk_timeout_job_fails_pending_payment(): void
    {
        Queue::fake();

        $invoice = $this->makeInvoice();
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'paid_via' => 'mpesa',
            'amount' => 500,
            'status' => 'pending',
            'mpesa_checkout_request_id' => 'ws_CO_timeout',
        ]);

        app(CollectionEngine::class)->timeoutPendingPayment($payment);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(CollectionStatus::Failed->value, $invoice->fresh()->collection_status);
        Queue::assertPushed(RequestCollectionPayment::class);
        Queue::assertNotPushed(WatchStkTimeout::class);
    }

    private function makeInvoice(): Invoice
    {
        return Invoice::create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-REC-'.uniqid(),
            'customer_name' => 'Buyer',
            'customer_phone' => '254712345678',
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [],
            'collection_status' => CollectionStatus::PendingPin->value,
        ]);
    }

    private function bindPaystackGateway(): void
    {
        $paystack = Mockery::mock(PaymentGateway::class);
        $paystack->shouldReceive('key')->andReturn('paystack');
        $paystack->shouldReceive('isConfigured')->andReturn(true);

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('get')->with('paystack')->andReturn($paystack);
        $manager->shouldReceive('get')->with('mpesa')->andReturn(null);
        $manager->shouldReceive('preferredForCompany')->andReturn($paystack);
        $this->app->instance(PaymentGatewayManager::class, $manager);
    }
}
