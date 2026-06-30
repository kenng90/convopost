<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\MpesaCallbackValidator;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Nodes\BookAppointment;
use Modules\Flowmaker\Models\Nodes\BookingEventRegister;
use Modules\Invoice\Models\InvoicePayment;
use Modules\Reminders\Services\BookingPaymentService;

class BookingMpesaController extends Controller
{
    public function __construct(
        private readonly BookingPaymentService $bookingPaymentService
    ) {
    }

    public function stkCallback(Request $request)
    {
        Log::info('Booking MPesa STK Callback received', ['payload' => $request->all()]);

        try {
            $body = $request->input('Body.stkCallback') ?? $request->input('Body');

            if (! $body) {
                Log::error('Booking MPesa STK Callback: invalid payload structure');

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            MpesaCallbackValidator::logCallback($body, 'received');

            $validation = MpesaCallbackValidator::validateStkPushCallback($body);

            if (! $validation['valid']) {
                Log::error('Booking MPesa STK Callback: invalid structure', ['errors' => $validation['errors']]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $checkoutRequestId = $body['CheckoutRequestID'] ?? null;

            if (! $checkoutRequestId) {
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            if (! MpesaCallbackValidator::isNotDuplicate($checkoutRequestId)) {
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $payment = InvoicePayment::findByCheckoutRequestId($checkoutRequestId);

            if (! $payment) {
                Log::warning('Booking MPesa STK Callback: payment not found', [
                    'checkoutRequestId' => $checkoutRequestId,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $payment->loadMissing('invoice');
            $invoice = $payment->invoice;

            if (! $invoice || ! $invoice->isBookingPayment()) {
                Log::warning('Booking MPesa STK Callback: not a booking payment', [
                    'paymentId' => $payment->id,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $company = Company::find($invoice->company_id);

            if (! $company) {
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $mpesaService = new MpesaService($company);
            $callbackData = $mpesaService->parseCallback($body);

            $payment->storeResponseData($body);

            if ($callbackData['success']) {
                $payment->markAsSuccess($callbackData['receipt_number']);
                $this->bookingPaymentService->fulfillSuccessfulPayment($payment);
                $this->resumeFlowmakerBookingIfNeeded($payment, 'success');
            } else {
                $payment->markAsFailed($callbackData['result_description']);
                $this->resumeFlowmakerBookingIfNeeded($payment, 'failed');
            }

            MpesaCallbackValidator::logCallback($body, 'processed');
        } catch (\Throwable $exception) {
            Log::error('Booking MPesa STK Callback: exception', [
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function resumeFlowmakerBookingIfNeeded(InvoicePayment $payment, string $outcome): void
    {
        $payment->loadMissing('invoice');
        $invoice = $payment->invoice;

        if (! $invoice?->isBookingPayment()) {
            return;
        }

        $notes = $invoice->fresh()->bookingNotes();
        $flowId = (int) ($notes['flow_id'] ?? 0);
        $contactId = (int) ($notes['contact_id'] ?? 0);
        $nodeId = (string) ($notes['flow_node_id'] ?? '');

        if (! $flowId || ! $contactId || $nodeId === '') {
            return;
        }

        if ($outcome === 'success') {
            $notes = $invoice->fresh()->bookingNotes();
            $reservationId = (int) ($notes['reservation_id'] ?? 0);
            $registrationId = (int) ($notes['event_registration_id'] ?? 0);
            $flow = \Modules\Flowmaker\Models\Flow::withoutGlobalScopes()->find($flowId);
            $contact = Contact::find($contactId);

            if ($flow && $contact && $reservationId) {
                $flow->resumeBookingPaymentSuccess($contact, $nodeId, $reservationId);

                return;
            }

            if ($flow && $contact && $registrationId) {
                $flow->resumeEventRegistrationPaymentSuccess($contact, $nodeId, $registrationId);

                return;
            }
        }

        $contact = Contact::find($contactId);

        if ($contact && $outcome === 'failed') {
            $contact->sendMessage(__('Payment was not completed, so your booking was not confirmed.'), false, false, 'TEXT');
        }

        $notes = $invoice->fresh()->bookingNotes();
        $bookingType = (string) ($notes['booking_type'] ?? '');

        if ($bookingType === BookingPaymentService::BOOKING_TYPE_EVENT) {
            BookingEventRegister::notifyPaymentOutcome($flowId, $contactId, $nodeId, $outcome);
        } else {
            BookAppointment::notifyPaymentOutcome($flowId, $contactId, $nodeId, $outcome);
        }
    }
}
