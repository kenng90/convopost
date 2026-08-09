<?php

namespace App\Services\Messaging\DTO;

final class ChannelHealth
{
    public function __construct(
        public readonly bool $healthy,
        public readonly string $message,
    ) {
    }
}
