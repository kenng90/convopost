<?php

namespace App\Services\Campaign;

class CampaignFileParser
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, row_count: int}
     */
    public function parseFromPath(string $path, string $extension): array
    {
        $ext = strtolower($extension);

        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseCsvFile($path);
        }

        return $this->parseXlsxFile($path);
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, row_count: int}
     */
    public function parseCsvFile(string $path): array
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $first = true;
            while (($row = fgetcsv($handle)) !== false) {
                if ($first) {
                    $headers = array_map('trim', $row);
                    $first = false;
                } else {
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, row_count: int}
     */
    public function parseXlsxFile(string $path): array
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException(__('Excel support requires phpoffice/phpspreadsheet.'));
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $headers = [];
        $first = true;

        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [];

            foreach ($cellIterator as $cell) {
                $rowData[] = trim((string) $cell->getValue());
            }

            if ($first) {
                $headers = $rowData;
                $first = false;
            } else {
                $rows[] = $rowData;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    public function normalizePhoneFromCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $value = number_format((float) $value, 0, '', '');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $value);

        return strlen($phone) >= 7 ? $phone : null;
    }

    public function normalizeEmailFromCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $email = trim((string) $value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function resolveColumnIndex(array $headers, string $column): int|false
    {
        $index = array_search($column, $headers, true);

        if ($index !== false) {
            return $index;
        }

        foreach ($headers as $idx => $header) {
            if (strcasecmp(trim((string) $header), trim($column)) === 0) {
                return $idx;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function countValidRecipientRows(array $rows, array $headers, int $columnIndex, string $channel): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            while (count($row) < count($headers)) {
                $row[] = '';
            }

            $value = $row[$columnIndex] ?? null;
            $valid = $channel === \Modules\Wpbox\Models\Campaign::CHANNEL_EMAIL
                ? $this->normalizeEmailFromCell($value) !== null
                : $this->normalizePhoneFromCell($value) !== null;

            if ($valid) {
                $count++;
            }
        }

        return $count;
    }
}
