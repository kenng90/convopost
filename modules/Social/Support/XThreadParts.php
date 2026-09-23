<?php

namespace Modules\Social\Support;

use Modules\Social\Models\SocialPostVersion;

class XThreadParts
{
    /**
     * Resolve ordered tweet texts for an X thread.
     * Tweet 1 is the version content; additional parts come from provider_payload.thread.
     *
     * @return list<string>
     */
    public static function fromVersion(SocialPostVersion $version): array
    {
        $first = trim((string) ($version->content ?? ''));
        $extra = data_get($version->provider_payload, 'thread', []);

        if (! is_array($extra)) {
            $extra = [];
        }

        $parts = [];

        if ($first !== '') {
            $parts[] = $first;
        }

        foreach ($extra as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @param  list<string|null>|null  $replies
     * @return list<string>
     */
    public static function normalizeReplies(?array $replies): array
    {
        if ($replies === null || $replies === []) {
            return [];
        }

        $normalized = [];

        foreach (array_slice($replies, 0, 24) as $reply) {
            $reply = trim((string) $reply);
            if ($reply === '') {
                continue;
            }
            $normalized[] = mb_substr($reply, 0, 280);
        }

        return $normalized;
    }
}
