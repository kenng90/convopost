<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\ContactState;
use Modules\Flowmaker\Models\Flow;

class MpesaController extends Controller
{
    /**
     * Handle the STK Push callback from Safaricom.
     * Safaricom POSTs to this endpoint after the customer completes or rejects payment.
     */
    public function stkCallback(Request $request)
    {
        Log::info('MPesa STK Callback received', ['payload' => $request->all()]);

        try {
            $body = $request->input('Body.stkCallback') ?? $request->input('Body');

            if (!$body) {
                Log::error('MPesa STK Callback: invalid payload structure', ['raw' => $request->all()]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
            $resultCode = $body['ResultCode'] ?? null;
            $resultDesc = $body['ResultDesc'] ?? '';

            Log::info('MPesa STK Callback: parsed', [
                'checkoutRequestId' => $checkoutRequestId,
                'resultCode' => $resultCode,
                'resultDesc' => $resultDesc,
            ]);

            if (!$checkoutRequestId) {
                Log::error('MPesa STK Callback: missing CheckoutRequestID');
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Find the contact and flow by the stored checkout request ID
            $checkoutState = ContactState::where('state', 'mpesa_checkout_request_id')
                ->where('value', $checkoutRequestId)
                ->first();

            if (!$checkoutState) {
                Log::warning('MPesa STK Callback: no matching checkout state found', ['checkoutRequestId' => $checkoutRequestId]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $contactId = $checkoutState->contact_id;
            $flowId = $checkoutState->flow_id;

            Log::info('MPesa STK Callback: found contact and flow', ['contactId' => $contactId, 'flowId' => $flowId]);

            $contact = Contact::find($contactId);
            $flow = Flow::withoutGlobalScopes()->find($flowId);

            if (!$contact || !$flow) {
                Log::error('MPesa STK Callback: contact or flow not found', ['contactId' => $contactId, 'flowId' => $flowId]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Extract payment details from successful callback metadata
            $receiptNumber = null;
            $mpesaAmount = null;

            if ($resultCode == 0 && isset($body['CallbackMetadata']['Item'])) {
                foreach ($body['CallbackMetadata']['Item'] as $item) {
                    match ($item['Name'] ?? '') {
                        'MpesaReceiptNumber' => $receiptNumber = $item['Value'] ?? null,
                        'Amount' => $mpesaAmount = $item['Value'] ?? null,
                        default => null,
                    };
                }
            }

            // Store all results in contact state so the MPesa node can read them when resumed
            $contact->setContactState($flowId, 'mpesa_result_code', (string) $resultCode);
            $contact->setContactState($flowId, 'mpesa_result_desc', $resultDesc);
            $contact->setContactState($flowId, 'mpesa_status', $resultCode == 0 ? 'success' : 'failed');

            if ($receiptNumber) {
                $contact->setContactState($flowId, 'mpesa_receipt', $receiptNumber);
            }
            if ($mpesaAmount) {
                $contact->setContactState($flowId, 'mpesa_amount_paid', (string) $mpesaAmount);
            }

            Log::info('MPesa STK Callback: state updated, resuming flow', [
                'resultCode' => $resultCode,
                'receiptNumber' => $receiptNumber,
            ]);

            // Resume the flow from the MPesa node (current_node is still set to it)
            $flow->resumeFromMpesaCallback($contact);

        } catch (\Exception $e) {
            Log::error('MPesa STK Callback: exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Always return 200 to Safaricom
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
