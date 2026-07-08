<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Tests\TestCase;

class FlowmakerMpesaCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_mpesa_callback_marks_invoice_payment_successful(): void
    {
        $company = Company::factory()->create();

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'phone' => '254712345678',
        ]);

        $records = Invoice::createForFlowStkPush(
            company: $company,
            customerName: 'Jane Doe',
            customerPhone: '254712345678',
            flowId: 99,
            nodeId: 'node-1',
            contactId: $contact->id,
            amount: 250.00,
            transactionDesc: 'Order payment',
            accountReference: 'ORDER-1',
        );

        /** @var InvoicePayment $payment */
        $payment = $records['payment'];
        $payment->update([
            'mpesa_checkout_request_id' => 'ws_CO_flow_test_123',
            'mpesa_merchant_request_id' => 'merchant-1',
            'initiated_at' => now(),
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_flow_test_123',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 250],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QAB999XYZ'],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/flowmaker/mpesa/callback', $payload);

        $response->assertOk()
            ->assertJson([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);

        $payment->refresh();
        $records['invoice']->refresh();

        $this->assertSame('success', $payment->status);
        $this->assertSame('QAB999XYZ', $payment->mpesa_receipt_number);
        $this->assertSame('paid', $records['invoice']->status);

        $this->assertTrue(
            Message::query()
                ->where('contact_id', $contact->id)
                ->where('is_note', true)
                ->where('value', 'like', '%'.$records['invoice']->invoice_number.'%')
                ->exists(),
            'Expected a contact note after successful M-Pesa payment sync.'
        );
    }

    public function test_flow_mpesa_callback_marks_invoice_payment_failed(): void
    {
        $company = Company::factory()->create();

        $records = Invoice::createForFlowStkPush(
            company: $company,
            customerName: 'Jane Doe',
            customerPhone: '254712345678',
            flowId: 99,
            nodeId: 'node-1',
            contactId: 42,
            amount: 250.00,
            transactionDesc: 'Order payment',
            accountReference: 'ORDER-1',
        );

        /** @var InvoicePayment $payment */
        $payment = $records['payment'];
        $payment->update([
            'mpesa_checkout_request_id' => 'ws_CO_flow_fail_123',
            'initiated_at' => now(),
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_flow_fail_123',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ];

        $this->postJson('/flowmaker/mpesa/callback', $payload)->assertOk();

        $payment->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertSame('Request cancelled by user', $payment->result_description);
    }
}
