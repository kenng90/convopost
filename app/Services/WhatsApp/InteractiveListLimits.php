<?php

namespace App\Services\WhatsApp;

/**
 * WhatsApp Cloud API interactive list message field limits.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/messages/interactive-list-messages
 */
class InteractiveListLimits
{
    public const HEADER = 60;

    public const BODY = 1024;

    public const FOOTER = 60;

    public const BUTTON = 20;

    public const SECTION_TITLE = 24;

    public const ROW_TITLE = 24;

    public const ROW_DESCRIPTION = 72;

    public const ROW_ID = 200;

    public static function truncate(?string $value, int $max): string
    {
        $value = (string) $value;

        if ($max < 1 || mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @param  array<int, array{id?: string, title?: string, description?: string}>  $rows
     * @return array{
     *     header: string,
     *     body: string,
     *     footer: string,
     *     button: string,
     *     section_title: string,
     *     rows: array<int, array{id: string, title: string, description: string}>
     * }
     */
    public static function constrainListFields(
        string $header,
        string $body,
        string $footer,
        string $button,
        string $sectionTitle,
        array $rows
    ): array {
        return [
            'header' => self::truncate($header, self::HEADER),
            'body' => self::truncate($body, self::BODY),
            'footer' => self::truncate($footer, self::FOOTER),
            'button' => self::truncate($button, self::BUTTON),
            'section_title' => self::truncate($sectionTitle, self::SECTION_TITLE),
            'rows' => collect($rows)->map(fn (array $row) => [
                'id' => self::truncate((string) ($row['id'] ?? ''), self::ROW_ID),
                'title' => self::truncate((string) ($row['title'] ?? ''), self::ROW_TITLE),
                'description' => self::truncate((string) ($row['description'] ?? ''), self::ROW_DESCRIPTION),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public static function constrainListAction(array $action): array
    {
        if (isset($action['button'])) {
            $action['button'] = self::truncate((string) $action['button'], self::BUTTON);
        }

        if (! isset($action['sections']) || ! is_array($action['sections'])) {
            return $action;
        }

        $action['sections'] = collect($action['sections'])->map(function ($section) {
            if (! is_array($section)) {
                return $section;
            }

            if (isset($section['title'])) {
                $section['title'] = self::truncate((string) $section['title'], self::SECTION_TITLE);
            }

            if (isset($section['rows']) && is_array($section['rows'])) {
                $section['rows'] = collect($section['rows'])->map(function ($row) {
                    if (! is_array($row)) {
                        return $row;
                    }

                    if (isset($row['id'])) {
                        $row['id'] = self::truncate((string) $row['id'], self::ROW_ID);
                    }
                    if (isset($row['title'])) {
                        $row['title'] = self::truncate((string) $row['title'], self::ROW_TITLE);
                    }
                    if (isset($row['description'])) {
                        $row['description'] = self::truncate((string) $row['description'], self::ROW_DESCRIPTION);
                    }

                    return $row;
                })->values()->all();
            }

            return $section;
        })->values()->all();

        return $action;
    }
}
