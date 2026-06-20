<?php

namespace App\Services\Messaging\DTO;

final class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalMessageId = null,
        public readonly ?string $error = null,
    ) {
    }
}
