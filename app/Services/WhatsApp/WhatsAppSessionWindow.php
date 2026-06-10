<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;
use Modules\Wpbox\Models\Contact;

class WhatsAppSessionWindow
{
    public const WINDOW_HOURS = 24;

    public function isOpen(?Contact $contact): bool
    {
        if ($contact === null || $contact->last_client_reply_at === null) {
            return false;
        }

        return Carbon::parse($contact->last_client_reply_at)
            ->addHours(self::WINDOW_HOURS)
            ->isFuture();
    }
}
