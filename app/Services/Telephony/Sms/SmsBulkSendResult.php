<?php

namespace App\Services\Telephony\Sms;

class SmsBulkSendResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public int $recipientCount = 0,
        public ?string $providerMessageId = null,
    ) {
    }

    public static function ok(int $recipientCount, ?string $id = null): self
    {
        return new self(true, 'Bulk SMS sent successfully', $recipientCount, $id);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
