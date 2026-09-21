<?php

namespace Modules\Social\Support;

class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerPostId = null,
        public readonly ?string $error = null,
        public readonly array $meta = [],
    ) {
    }

    public static function ok(string $providerPostId, array $meta = []): self
    {
        return new self(true, $providerPostId, null, $meta);
    }

    public static function fail(string $error, array $meta = []): self
    {
        return new self(false, null, $error, $meta);
    }
}
