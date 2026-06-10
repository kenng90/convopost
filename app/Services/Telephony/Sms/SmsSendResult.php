<?php

namespace App\Services\Telephony\Sms;

class SmsSendResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?string $providerMessageId = null,
    ) {
    }

    public static function ok(?string $id = null): self
    {
        return new self(true, 'SMS sent successfully', $id);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
