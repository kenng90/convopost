<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;
use Modules\Wpbox\Models\Contact;

class WhatsAppSessionWindow
{
    public const WINDOW_HOURS = 24;

    public function isOpen(?Contact $contact): bool
    {
        $expiresAt = $this->expiresAt($contact);

        return $expiresAt !== null && $expiresAt->isFuture();
    }

    public function expiresAt(?Contact $contact): ?Carbon
    {
        $customerRepliedAt = $this->lastCustomerMessageAt($contact);

        if ($customerRepliedAt === null) {
            return null;
        }

        return $customerRepliedAt->copy()->addHours($this->windowHours());
    }

    public function lastCustomerMessageAt(?Contact $contact): ?Carbon
    {
        if ($contact === null) {
            return null;
        }

        if ($contact->last_client_reply_at) {
            return Carbon::parse($contact->last_client_reply_at);
        }

        if ($contact->is_last_message_by_contact && $contact->last_reply_at) {
            return Carbon::parse($contact->last_reply_at);
        }

        return null;
    }

    public function windowHours(): int
    {
        $hours = (int) config('credit-actions.service_window_hours', self::WINDOW_HOURS);

        return $hours > 0 ? $hours : self::WINDOW_HOURS;
    }
}
