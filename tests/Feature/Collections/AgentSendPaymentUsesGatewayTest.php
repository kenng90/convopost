<?php

namespace Tests\Feature\Collections;

use App\Contracts\PaymentGateway;
use App\Enums\CollectionStatus;
use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\Agents\AgentToolRegistry;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Invoice\Models\InvoicePayment;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgentSendPaymentUsesGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_payment_goes_through_collection_engine(): void
    {
        Queue::fake();
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

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'phone' => '254712345678',
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('key')->andReturn('mpesa');
        $gateway->shouldReceive('isConfigured')->andReturn(true);
        $gateway->shouldReceive('initiate')->once()->andReturnUsing(function ($company, $invoice) {
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_method' => 'mpesa',
                'paid_via' => 'mpesa',
                'amount' => $invoice->amount,
                'status' => 'pending',
                'mpesa_checkout_request_id' => 'ws_CO_agent',
                'initiated_at' => now(),
            ]);

            return ['success' => true, 'payment' => $payment, 'message' => 'STK sent'];
        });

        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('preferredForCompany')->andReturn($gateway);
        $manager->shouldReceive('get')->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $result = app(AgentToolRegistry::class)->sendPayment($company, $contact, [
            'amount' => 750,
            'description' => 'Consultation',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['invoice_id']);
        $this->assertDatabaseHas('invoices', [
            'id' => $result['invoice_id'],
            'collection_status' => CollectionStatus::PendingPin->value,
        ]);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $result['invoice_id'],
            'payment_method' => 'mpesa',
            'status' => 'pending',
        ]);
    }
}
