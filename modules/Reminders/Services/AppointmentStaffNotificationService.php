<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\Reservation;
use Modules\Wpbox\Traits\Contacts;

class AppointmentStaffNotificationService
{
    use Contacts;

    public function notifyBooked(Reservation $reservation): void
    {
        $this->send($reservation, 'New booking');
    }

    public function notifyCancelled(Reservation $reservation): void
    {
        $this->send($reservation, 'Booking cancelled');
    }

    public function notifyRescheduled(Reservation $reservation): void
    {
        $this->send($reservation, 'Booking rescheduled');
    }

    private function send(Reservation $reservation, string $prefix): void
    {
        $member = $reservation->appointmentStaffMember;
        if (! $member || ! $member->whatsapp_phone) {
            return;
        }

        $company = Company::find($reservation->company_id);
        if (! $company) {
            return;
        }

        session(['company_id' => $company->id]);

        try {
            $contact = $this->getOrMakeContact($member->whatsapp_phone, $company, $member->name);
            $sourceName = $reservation->source?->name ?? 'Service';
            $clientName = $reservation->contact?->name ?? 'Client';
            $when = Carbon::parse($reservation->start_date)->format('D j M Y, H:i');

            $message = "{$prefix}: {$sourceName} with {$clientName} on {$when}.";

            $contact->sendMessage($message, false, false, 'TEXT');
        } catch (\Throwable $exception) {
            Log::warning('Appointment staff WhatsApp notification failed', [
                'reservation_id' => $reservation->id,
                'appointment_staff_id' => $member->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
