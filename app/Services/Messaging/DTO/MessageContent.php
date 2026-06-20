<?php

namespace App\Services\Messaging\DTO;

final class MessageContent
{
    public function __construct(
        public readonly string $type,
        public readonly string $body,
        public readonly ?string $mediaUrl = null,
    ) {
    }

    public static function text(string $body): self
    {
        return new self('TEXT', $body);
    }

    public static function image(string $url): self
    {
        return new self('IMAGE', $url, $url);
    }

    public function preview(): string
    {
        if ($this->type === 'TEXT') {
            return $this->body;
        }

        return '['.$this->type.']';
    }
}
