<?php

namespace App\Support;

class FlowBuilderScreenValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $screens
     * @return array<int, string>
     */
    public static function duplicateFieldIdErrors(array $screens): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($screens as $screen) {
            foreach ($screen['fields'] ?? [] as $field) {
                if (! array_key_exists('id', $field)) {
                    continue;
                }

                $key = (string) $field['id'];

                if (isset($seen[$key])) {
                    $duplicates[$key] = true;
                }

                $seen[$key] = true;
            }
        }

        if ($duplicates === []) {
            return [];
        }

        return [
            'Duplicate component field ids detected ('.implode(', ', array_keys($duplicates)).'). '
            .'Assign unique ids to each component.',
        ];
    }
}
