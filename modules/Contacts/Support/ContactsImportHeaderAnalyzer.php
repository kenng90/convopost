<?php

namespace Modules\Contacts\Support;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class ContactsImportHeaderAnalyzer
{
    /**
     * @return array<string, list<string>>
     */
    public static function findDuplicateHeadingGroups(array $rawHeadings): array
    {
        $groups = [];

        foreach ($rawHeadings as $heading) {
            if ($heading === null || trim((string) $heading) === '') {
                continue;
            }

            $heading = (string) $heading;
            $groups[Str::slug($heading, '_')][] = $heading;
        }

        return array_filter($groups, fn (array $headings) => count($headings) > 1);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function analyze($file): array
    {
        return self::findDuplicateHeadingGroups(self::extractRawHeadings($file));
    }

    /**
     * @return list<string>
     */
    public static function extractRawHeadings($file): array
    {
        $previousFormatter = config('excel.imports.heading_row.formatter');
        HeadingRowFormatter::default(HeadingRowFormatter::FORMATTER_NONE);

        try {
            $rows = Excel::toArray(new HeadingRowImport, $file);

            return array_values(array_filter(
                $rows[0][0] ?? [],
                fn ($heading) => $heading !== null && trim((string) $heading) !== ''
            ));
        } finally {
            HeadingRowFormatter::default($previousFormatter);
        }
    }

    /**
     * @param  array<string, list<string>>  $duplicateGroups
     */
    public static function formatDuplicateMessage(array $duplicateGroups): string
    {
        $details = [];

        foreach ($duplicateGroups as $slug => $headings) {
            $quotedHeadings = implode(', ', array_map(
                fn (string $heading) => "'{$heading}'",
                $headings
            ));

            $details[] = "{$slug} ({$quotedHeadings})";
        }

        return __('Duplicate column headers detected: :columns. Only the first non-empty value in each group will be used. Consider removing or renaming duplicate columns.', [
            'columns' => implode('; ', $details),
        ]);
    }
}
