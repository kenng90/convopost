<?php

namespace App\Services\Messaging;

final class MetaCommentReply
{
    public const SOURCE_COMMENT = 'comment';

    public const EXTRA_INBOUND = 'comment';

    public const EXTRA_PUBLIC = 'comment_public';

    public const EXTRA_PRIVATE = 'comment_private';

    public const MODE_PUBLIC = 'public_comment';

    public const MODE_PRIVATE = 'private_comment';

    public const MODE_DIRECT = 'direct';

    public const PRIVATE_WINDOW_DAYS = 7;

    public static function extraToMode(?string $extra): ?string
    {
        return match ((string) $extra) {
            self::EXTRA_PUBLIC => self::MODE_PUBLIC,
            self::EXTRA_PRIVATE => self::MODE_PRIVATE,
            default => null,
        };
    }

    public static function requestModeToExtra(?string $mode): ?string
    {
        return match ($mode) {
            'public', self::MODE_PUBLIC => self::EXTRA_PUBLIC,
            'private', self::MODE_PRIVATE => self::EXTRA_PRIVATE,
            default => null,
        };
    }
}
