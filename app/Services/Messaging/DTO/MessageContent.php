<?php

namespace App\Services\Messaging\DTO;

final class MessageContent
{
    /**
     * @param  array<int, array{content_type?: string, title: string, payload: string}>|null  $quickReplies
     */
    public function __construct(
        public readonly string $type,
        public readonly string $body,
        public readonly ?string $mediaUrl = null,
        public readonly ?array $quickReplies = null,
        public readonly ?string $extra = null,
    ) {
    }

    public static function text(string $body, ?string $extra = null): self
    {
        return new self('TEXT', $body, null, null, $extra);
    }

    /**
     * @param  array<int, array{content_type?: string, title: string, payload: string}>  $quickReplies
     */
    public static function textWithQuickReplies(string $body, array $quickReplies): self
    {
        return new self('TEXT', $body, null, $quickReplies);
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
