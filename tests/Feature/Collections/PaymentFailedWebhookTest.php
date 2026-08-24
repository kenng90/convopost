<?php

namespace Tests\Feature\Collections;

use App\Enums\CollectionStatus;
use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\Api\PublicWebhookDispatcher;
use App\Services\Collections\CollectionFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentFailedWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_failure_dispatches_payment_failed_webhook(): void
    {
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

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-FAIL-1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254712345678',
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [],
            'collection_status' => CollectionStatus::Chasing->value,
            'chase_step' => 4,
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'amount' => 500,
            'status' => 'failed',
            'result_description' => 'Unpaid after collection chase',
        ]);

        $dispatcher = Mockery::mock(PublicWebhookDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($companyId, $type, $data) use ($company, $invoice, $payment) {
                return (int) $companyId === (int) $company->id
                    && $type === 'payment.failed'
                    && ($data['invoice_id'] ?? null) === $invoice->id
                    && ($data['payment_id'] ?? null) === $payment->id;
            })
            ->andReturn('evt_test');
        $this->app->instance(PublicWebhookDispatcher::class, $dispatcher);

        app(CollectionFulfillment::class)->onTerminalFailure($invoice, $payment, 'Unpaid after collection chase');

        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame(CollectionStatus::Cancelled->value, $invoice->fresh()->collection_status);
    }
}
