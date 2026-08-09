<?php

namespace App\Services\Messaging\DTO;

final class ChannelCapabilities
{
    public function __construct(
        public readonly bool $text = true,
        public readonly bool $media = false,
        public readonly bool $templates = false,
        public readonly bool $campaigns = false,
        public readonly bool $flows = false,
        public readonly bool $requiresServiceWindow = false,
        public readonly ?int $serviceWindowHours = null,
    ) {
    }
}
