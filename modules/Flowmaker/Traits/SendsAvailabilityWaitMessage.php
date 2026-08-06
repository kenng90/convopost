<?php

namespace Modules\Flowmaker\Traits;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

trait SendsAvailabilityWaitMessage
{
    protected function notifyLookingUpAvailability(Contact $contact, string $kind): void
    {
        $text = match ($kind) {
            'dates' => __('One moment, fetching available dates…'),
            'times' => __('One moment, fetching available times…'),
            default => __('One moment, checking availability…'),
        };

        try {
            $company = Company::find($contact->company_id);
            $token = $company?->getConfig('plain_token', '') ?? '';

            if ($token === '') {
                Log::warning('Availability wait message skipped: missing plain_token', [
                    'contact_id' => $contact->id,
                    'company_id' => $contact->company_id,
                    'kind' => $kind,
                ]);

                return;
            }

            // Use the same HTTP API path as flow list/question messages so queue
            // workers get correct company WhatsApp credentials and billing context.
            $response = Http::post(config('app.url').'/api/wpbox/sendmessage', [
                'token' => $token,
                'phone' => $contact->phone,
                'message' => $text,
            ]);

            if ($response->failed()) {
                Log::warning('Availability wait message failed', [
                    'contact_id' => $contact->id,
                    'kind' => $kind,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Availability wait message error', [
                'contact_id' => $contact->id,
                'kind' => $kind,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
