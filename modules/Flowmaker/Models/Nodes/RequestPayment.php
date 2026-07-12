<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Invoice\Models\Invoice;

class RequestPayment extends Node
{
    public function listenForReply($message, $data)
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }

        $status = $contact->getContactStateValue($this->flow_id, 'payment_result_status');
        $contact->clearContactState($this->flow_id, 'current_node');

        $nextNode = ($status === 'success')
            ? $this->getNextNodeId('success')
            : $this->getNextNodeId('failed');

        if ($nextNode) {
            $nextNode->process($message, $data);
        }
    }

    public function process($message, $data)
    {
        if ($this->isStartNode) {
            $this->listenForReply($message, $data);

            return ['success' => true];
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        if (! $contact) {
            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $payment = $settings['payment'] ?? [];

        $amount = (float) $contact->changeVariables($payment['amount'] ?? '0', $this->flow_id);
        $description = $contact->changeVariables($payment['description'] ?? 'Payment', $this->flow_id);
        $accountReference = $contact->changeVariables($payment['accountReference'] ?? 'ORDER', $this->flow_id);
        $provider = strtolower((string) ($payment['provider'] ?? 'auto'));
        $email = $contact->changeVariables($payment['email'] ?? '', $this->flow_id);

        $company = Company::find($contact->company_id);
        if (! $company || $amount <= 0) {
            return ['success' => false];
        }

        $manager = app(PaymentGatewayManager::class);
        $gateway = $provider === 'auto'
            ? $manager->preferredForCompany($company)
            : $manager->get($provider);

        if (! $gateway || ! $gateway->isConfigured($company)) {
            $contact->sendMessage(
                __('Payments are not configured yet. Please speak with an agent.'),
                false,
                false,
                'TEXT'
            );

            return ['success' => false, 'error' => 'payment_not_configured'];
        }

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => Invoice::generateInvoiceNumber($company),
            'customer_name' => $contact->name ?: 'Customer',
            'customer_phone' => $contact->phone,
            'customer_email' => $email !== '' ? $email : null,
            'amount' => $amount,
            'currency' => strtoupper((string) ($company->getConfig('currency', 'KES') ?: 'KES')),
            'status' => 'sent',
            'description' => $description,
            'items' => [[
                'title' => $description,
                'quantity' => 1,
                'price' => $amount,
                'total' => $amount,
            ]],
            'notes' => [
                'source' => Invoice::SOURCE_FLOW,
                'flow_id' => $this->flow_id,
                'node_id' => $this->id,
                'contact_id' => $contact->id,
            ],
        ]);

        $result = $gateway->initiate($company, $invoice, [
            'phone' => $contact->phone,
            'email' => $email !== '' ? $email : null,
            'account_reference' => mb_substr($accountReference, 0, 12),
            'description' => mb_substr($description, 0, 13),
            'callback_url' => $gateway->key() === 'paystack'
                ? url('/flowmaker/paystack/callback')
                : url('/flowmaker/mpesa/callback'),
        ]);

        if (! ($result['success'] ?? false)) {
            Log::warning('RequestPayment initiate failed', [
                'provider' => $gateway->key(),
                'message' => $result['message'] ?? null,
            ]);
            $contact->setContactState($this->flow_id, 'payment_result_status', 'failed');
            $failed = $this->getNextNodeId('failed');
            if ($failed) {
                $failed->process($message, $data);
            }

            return ['success' => false];
        }

        $paymentModel = $result['payment'] ?? null;
        $contact->setContactState($this->flow_id, 'current_node', $this->id);
        $contact->setContactState($this->flow_id, 'payment_provider', $gateway->key());
        $contact->setContactState($this->flow_id, 'payment_invoice_id', (string) $invoice->id);
        if ($paymentModel) {
            $contact->setContactState($this->flow_id, 'payment_id', (string) $paymentModel->id);
            $contact->setContactState(
                $this->flow_id,
                'payment_reference',
                (string) ($paymentModel->gateway_reference ?: $paymentModel->mpesa_checkout_request_id)
            );
        }

        if ($gateway->key() === 'paystack' && ! empty($result['authorization_url'])) {
            $contact->sendMessage(
                __('Please complete payment here:')."\n".$result['authorization_url'],
                false,
                false,
                'TEXT'
            );
        } else {
            $contact->sendMessage(
                __('We sent an M-Pesa payment prompt. Enter your PIN to confirm.'),
                false,
                false,
                'TEXT'
            );
        }

        return ['success' => true, 'waiting' => true];
    }
}
