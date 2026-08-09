<?php

namespace App\Services\Messaging\DTO;

final class InboundBatch
{
    /**
     * @param  array<int, InboundMessage>  $messages
     */
    public function __construct(
        public readonly array $messages = [],
        public readonly bool $isStatusUpdate = false,
    ) {
    }
}
