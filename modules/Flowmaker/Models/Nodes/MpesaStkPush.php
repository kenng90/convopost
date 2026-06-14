<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Billing\CreditCharger;
use App\Services\MpesaService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class MpesaStkPush extends Node
{
    /**
     * Called when the callback from Safaricom arrives.
     * The contact state already has the mpesa result stored by the controller.
     */
    public function listenForReply($message, $data)
    {
        Log::info('MPesa STK Push: processing callback', ['nodeId' => $this->id, 'flowId' => $this->flow_id]);

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            Log::error('MPesa STK Push: contact not found', ['contactId' => $contactId]);

            return;
        }

        $resultCode = $contact->getContactStateValue($this->flow_id, 'mpesa_result_code');

        Log::info('MPesa STK Push: callback result', ['resultCode' => $resultCode, 'nodeId' => $this->id]);

        $contact->clearContactState($this->flow_id, 'current_node');
        $contact->clearContactState($this->flow_id, 'mpesa_checkout_request_id');
        $contact->clearContactState($this->flow_id, 'mpesa_payment_id');

        if ($resultCode == '0' || $resultCode === 0) {
            Log::info('MPesa STK Push: payment successful, routing to success handle');
            $nextNode = $this->getNextNodeId('success');
        } else {
            Log::info('MPesa STK Push: payment failed/cancelled, routing to failed handle', ['resultCode' => $resultCode]);
            $nextNode = $this->getNextNodeId('failed');
        }

        if ($nextNode) {
            $nextNode->process($message, $data);
        } else {
            Log::info('MPesa STK Push: no next node found for result', ['resultCode' => $resultCode]);
        }
    }

    public function process($message, $data)
    {
        Log::info('MPesa STK Push node: process called', ['isStartNode' => $this->isStartNode, 'nodeId' => $this->id]);

        if ($this->isStartNode) {
            $this->listenForReply($message, $data);

            return ['success' => true];
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            Log::error('MPesa STK Push: contact not found', ['contactId' => $contactId]);

            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $mpesaSettings = $settings['mpesa'] ?? [];

        $amount = (float) $contact->changeVariables($mpesaSettings['amount'] ?? '1', $this->flow_id);
        $accountReference = $contact->changeVariables($mpesaSettings['accountReference'] ?? 'Payment', $this->flow_id);
        $transactionDesc = $contact->changeVariables($mpesaSettings['transactionDesc'] ?? 'Payment', $this->flow_id);
        $responseVar = $mpesaSettings['responseVar'] ?? 'mpesa_result';

        $company = Company::find($contact->company_id);

        if ($company === null) {
            Log::error('MPesa STK Push: company not found', ['companyId' => $contact->company_id]);

            return ['success' => false];
        }

        $charger = app(CreditCharger::class);
        $creditAction = 'mpesa_stk_push';

        if (! $charger->canCharge($company, $creditAction)) {
            Log::warning('MPesa STK Push blocked: insufficient credits', [
                'contact_id' => $contact->id,
                'company_id' => $company->id,
            ]);
            $contact->setContactState($this->flow_id, $responseVar.'_status', 'insufficient_credits');

            return ['success' => false, 'error' => 'insufficient_credits'];
        }

        $mpesaService = new MpesaService($company);

        if (! $mpesaService->isConfigured()) {
            Log::error('MPesa STK Push: M-Pesa not configured', [
                'companyId' => $company->id,
                'errors' => $mpesaService->getConfigErrors(),
            ]);
            $contact->setContactState($this->flow_id, $responseVar.'_error', 'M-Pesa is not configured');
            $this->routeToFailed($message, $data, $contact);

            return ['success' => false];
        }

        Log::info('MPesa STK Push: initiating', [
            'phone' => $contact->phone,
            'amount' => $amount,
            'accountReference' => $accountReference,
        ]);

        try {
            $records = Invoice::createForFlowStkPush(
                company: $company,
                customerName: $contact->name ?? 'Flow Contact',
                customerPhone: $contact->phone,
                flowId: (int) $this->flow_id,
                nodeId: (string) $this->id,
                contactId: (int) $contact->id,
                amount: $amount,
                transactionDesc: $transactionDesc,
                accountReference: $accountReference,
            );

            /** @var InvoicePayment $payment */
            $payment = $records['payment'];

            $result = $mpesaService->initiateStk(
                phone: $contact->phone,
                amount: $amount,
                accountReference: $accountReference,
                transactionDesc: $transactionDesc,
                callbackUrl: config('app.url').'/flowmaker/mpesa/callback',
            );

            if (! $result['success']) {
                Log::error('MPesa STK Push: STK request failed', ['error' => $result['error'] ?? 'Unknown error']);
                $payment->markAsFailed($result['error'] ?? 'STK push failed');
                $contact->setContactState($this->flow_id, $responseVar.'_error', $result['error'] ?? 'STK push failed');
                $this->routeToFailed($message, $data, $contact, $payment);

                return ['success' => false];
            }

            $checkoutRequestId = $result['checkout_request_id'];

            $payment->update([
                'mpesa_checkout_request_id' => $checkoutRequestId,
                'mpesa_merchant_request_id' => $result['merchant_request_id'] ?? '',
                'initiated_at' => now(),
            ]);

            $charger->charge($company, $creditAction, $company->id);

            $contact->setContactState($this->flow_id, 'mpesa_checkout_request_id', $checkoutRequestId);
            $contact->setContactState($this->flow_id, 'mpesa_merchant_request_id', $result['merchant_request_id'] ?? '');
            $contact->setContactState($this->flow_id, 'mpesa_payment_id', (string) $payment->id);
            $contact->setContactState($this->flow_id, $responseVar.'_status', 'pending');
            $contact->setContactState($this->flow_id, 'current_node', $this->id);

            Log::info('MPesa STK Push: waiting for callback', [
                'checkoutRequestId' => $checkoutRequestId,
                'paymentId' => $payment->id,
                'nodeId' => $this->id,
                'contactId' => $contact->id,
                'flowId' => $this->flow_id,
            ]);
        } catch (\Exception $e) {
            Log::error('MPesa STK Push: exception', ['error' => $e->getMessage()]);
            $contact->setContactState($this->flow_id, $responseVar.'_error', $e->getMessage());
            $this->routeToFailed($message, $data, $contact);
        }

        return ['success' => true];
    }

    private function routeToFailed($message, $data, Contact $contact, ?InvoicePayment $payment = null): void
    {
        if ($payment === null) {
            $paymentId = $contact->getContactStateValue($this->flow_id, 'mpesa_payment_id');
            if ($paymentId) {
                $payment = InvoicePayment::find($paymentId);
            }
        }

        if ($payment && $payment->isPending()) {
            $payment->markAsFailed('STK push failed before callback');
        }

        $contact->clearContactState($this->flow_id, 'current_node');
        $contact->clearContactState($this->flow_id, 'mpesa_payment_id');

        $failedNode = $this->getNextNodeId('failed');
        if ($failedNode) {
            $failedNode->process($message, $data);
        }
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            if ($handleId === null || str_contains($edge->getSourceHandle() ?? '', $handleId)) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
