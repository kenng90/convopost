<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Billing\CreditCharger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

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

        // Read the result code stored by the callback controller
        $resultCode = $contact->getContactStateValue($this->flow_id, 'mpesa_result_code');
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $responseVar = $settings['responseVar'] ?? 'mpesa_result';

        Log::info('MPesa STK Push: callback result', ['resultCode' => $resultCode, 'nodeId' => $this->id]);

        // Clear the waiting state
        $contact->clearContactState($this->flow_id, 'current_node');
        $contact->clearContactState($this->flow_id, 'mpesa_checkout_request_id');

        // Route based on result code (0 = success)
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
            // We are resuming after the Safaricom callback
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

        $amount = $contact->changeVariables($mpesaSettings['amount'] ?? '1', $this->flow_id);
        $accountReference = $contact->changeVariables($mpesaSettings['accountReference'] ?? 'Payment', $this->flow_id);
        $transactionDesc = $contact->changeVariables($mpesaSettings['transactionDesc'] ?? 'Payment', $this->flow_id);
        $responseVar = $mpesaSettings['responseVar'] ?? 'mpesa_result';
        $phone = $this->formatMpesaPhone($contact->phone);

        // Get MPesa credentials from company config
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

        $consumerKey = $company->getConfig('mpesa_consumer_key', '');
        $consumerSecret = $company->getConfig('mpesa_consumer_secret', '');
        $passkey = $company->getConfig('mpesa_passkey', '');
        $shortCode = $company->getConfig('mpesa_short_code', '');
        $environment = $company->getConfig('mpesa_environment', 'sandbox');

        $baseUrl = $environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        Log::info('MPesa STK Push: initiating', [
            'phone' => $phone,
            'amount' => $amount,
            'accountReference' => $accountReference,
            'environment' => $environment,
        ]);

        try {
            // Step 1: Generate OAuth access token
            $tokenResponse = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get("{$baseUrl}/oauth/v1/generate", ['grant_type' => 'client_credentials']);

            if (! $tokenResponse->successful()) {
                Log::error('MPesa STK Push: failed to get access token', ['response' => $tokenResponse->body()]);
                $contact->setContactState($this->flow_id, $responseVar.'_error', 'Failed to authenticate with MPesa');
                $this->routeToFailed($message, $data, $contact);

                return ['success' => false];
            }

            $accessToken = $tokenResponse->json('access_token');

            // Step 2: Build STK Push payload
            $timestamp = now()->format('YmdHis');
            $password = base64_encode($shortCode.$passkey.$timestamp);
            $callbackUrl = config('app.url').'/flowmaker/mpesa/callback';

            $stkPayload = [
                'BusinessShortCode' => $shortCode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $amount,
                'PartyA' => $phone,
                'PartyB' => $shortCode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => substr($accountReference, 0, 12),
                'TransactionDesc' => substr($transactionDesc, 0, 13),
            ];

            Log::info('MPesa STK Push: sending STK push', ['payload' => $stkPayload]);

            // Step 3: Send STK Push
            $stkResponse = Http::timeout(30)
                ->withToken($accessToken)
                ->post("{$baseUrl}/mpesa/stkpush/v1/processrequest", $stkPayload);

            $stkData = $stkResponse->json();
            Log::info('MPesa STK Push: STK response', ['response' => $stkData]);

            if (! $stkResponse->successful() || isset($stkData['errorCode'])) {
                $error = $stkData['errorMessage'] ?? $stkData['ResultDesc'] ?? 'STK push failed';
                Log::error('MPesa STK Push: STK request failed', ['error' => $error, 'response' => $stkData]);
                $contact->setContactState($this->flow_id, $responseVar.'_error', $error);
                $this->routeToFailed($message, $data, $contact);

                return ['success' => false];
            }

            $checkoutRequestId = $stkData['CheckoutRequestID'] ?? null;

            if (! $checkoutRequestId) {
                Log::error('MPesa STK Push: no CheckoutRequestID in response', ['response' => $stkData]);
                $this->routeToFailed($message, $data, $contact);

                return ['success' => false];
            }

            $charger->charge($company, $creditAction, $company->id);

            // Step 4: Store state to wait for callback
            $contact->setContactState($this->flow_id, 'mpesa_checkout_request_id', $checkoutRequestId);
            $contact->setContactState($this->flow_id, 'mpesa_merchant_request_id', $stkData['MerchantRequestID'] ?? '');
            $contact->setContactState($this->flow_id, $responseVar.'_status', 'pending');
            $contact->setContactState($this->flow_id, 'current_node', $this->id);

            Log::info('MPesa STK Push: waiting for callback', [
                'checkoutRequestId' => $checkoutRequestId,
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

    /**
     * Format the phone number to Safaricom's required format (254XXXXXXXXX)
     */
    private function formatMpesaPhone(string $phone): string
    {
        // Remove any + prefix
        $phone = ltrim($phone, '+');

        // If starts with 0, replace with 254
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        // If starts with 7 or 1 (local format without country code)
        if (strlen($phone) === 9 && (str_starts_with($phone, '7') || str_starts_with($phone, '1'))) {
            $phone = '254'.$phone;
        }

        return $phone;
    }

    /**
     * Route to the failed handle without waiting for callback
     */
    private function routeToFailed($message, $data, $contact)
    {
        $contact->clearContactState($this->flow_id, 'current_node');
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
