<?php

namespace App\Services\Messaging\DTO;

use Carbon\Carbon;

final class InboundMessage
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $externalMessageId,
        public readonly string $externalParticipantId,
        public readonly ?string $participantName,
        public readonly MessageContent $content,
        public readonly Carbon $receivedAt,
        public readonly array $raw = [],
        public readonly ?string $extra = null,
        public readonly array $context = [],
    ) {
    }

    public function isComment(): bool
    {
        return ($this->context['source'] ?? null) === 'comment';
    }
}
