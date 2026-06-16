<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\EventRegistration;
use Modules\Wpbox\Traits\Contacts;

class EventHostNotificationService
{
    use Contacts;

    public function notifyRegistered(EventRegistration $registration): void
    {
        $this->send($registration, 'New event registration');
    }

    public function notifyCancelled(EventRegistration $registration): void
    {
        $this->send($registration, 'Event registration cancelled');
    }

    private function send(EventRegistration $registration, string $prefix): void
    {
        $host = $registration->event?->host;

        if (! $host || ! $host->whatsapp_phone) {
            return;
        }

        $company = Company::find($registration->company_id);

        if (! $company) {
            return;
        }

        session(['company_id' => $company->id]);

        try {
            $contact = $this->getOrMakeContact($host->whatsapp_phone, $company, $host->name);
            $eventTitle = $registration->event?->title ?? 'Event';
            $clientName = $registration->contact?->name ?? 'Guest';
            $when = Carbon::parse($registration->occurrence?->starts_at)->format('D j M Y, H:i');
            $partySize = (int) $registration->party_size;

            $message = "{$prefix}: {$eventTitle} — {$clientName} ({$partySize} ".($partySize === 1 ? 'seat' : 'seats').") on {$when}.";

            $contact->sendMessage($message, false, false, 'TEXT');
        } catch (\Throwable $exception) {
            Log::warning('Event host WhatsApp notification failed', [
                'registration_id' => $registration->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
