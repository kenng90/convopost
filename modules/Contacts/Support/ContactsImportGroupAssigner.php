<?php

namespace Modules\Contacts\Support;

use Illuminate\Support\Facades\Storage;
use Modules\Contacts\Models\Contact;
use Modules\Contacts\Models\ContactImport;
use Modules\Contacts\Models\Group;

class ContactsImportGroupAssigner
{
    private const PHONE_BATCH_SIZE = 500;

    private const ATTACH_BATCH_SIZE = 500;

    public static function assign(ContactImport $contactImport): void
    {
        if (! $contactImport->group_id) {
            return;
        }

        session(['company_id' => $contactImport->company_id]);

        $group = Group::find($contactImport->group_id);

        if (! $group) {
            return;
        }

        $disk = Storage::disk($contactImport->disk);
        $path = $contactImport->file_path;

        if (! $disk->exists($path)) {
            return;
        }

        $absolutePath = $disk->path($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            self::assignFromCsvFile($group, $contactImport, $absolutePath);

            return;
        }

        self::assignFromSpreadsheet($group, $contactImport);
    }

    private static function assignFromCsvFile(Group $group, ContactImport $contactImport, string $absolutePath): void
    {
        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            return;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return;
        }

        $phoneIndex = self::resolvePhoneColumnIndex($headers);
        $phoneBatch = [];

        while (($row = fgetcsv($handle)) !== false) {
            $phone = self::normalizePhoneFromRow($row[$phoneIndex] ?? null);

            if ($phone === null) {
                continue;
            }

            $phoneBatch[] = $phone;

            if (count($phoneBatch) >= self::PHONE_BATCH_SIZE) {
                self::attachPhonesToGroup($group, $contactImport->company_id, $phoneBatch);
                $phoneBatch = [];
            }
        }

        fclose($handle);

        if ($phoneBatch !== []) {
            self::attachPhonesToGroup($group, $contactImport->company_id, $phoneBatch);
        }
    }

    private static function assignFromSpreadsheet(Group $group, ContactImport $contactImport): void
    {
        $csvData = \Maatwebsite\Excel\Facades\Excel::toArray(
            new \Modules\Contacts\Imports\ContactsImport($contactImport->id),
            $contactImport->file_path,
            $contactImport->disk
        );

        $phoneBatch = [];

        foreach ($csvData[0] ?? [] as $row) {
            $phoneValue = $row['phone'] ?? null;

            if (is_array($phoneValue)) {
                foreach ($phoneValue as $item) {
                    if ($item !== null && trim((string) $item) !== '') {
                        $phoneValue = $item;
                        break;
                    }
                }
            }

            $phone = self::normalizePhoneFromRow($phoneValue);

            if ($phone === null) {
                continue;
            }

            $phoneBatch[] = $phone;

            if (count($phoneBatch) >= self::PHONE_BATCH_SIZE) {
                self::attachPhonesToGroup($group, $contactImport->company_id, $phoneBatch);
                $phoneBatch = [];
            }
        }

        if ($phoneBatch !== []) {
            self::attachPhonesToGroup($group, $contactImport->company_id, $phoneBatch);
        }
    }

    /**
     * @param  list<string>  $headers
     */
    private static function resolvePhoneColumnIndex(array $headers): int
    {
        foreach ($headers as $index => $header) {
            if (strtolower(trim((string) $header)) === 'phone') {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @param  list<string>  $phoneNumbers
     */
    private static function attachPhonesToGroup(Group $group, int $companyId, array $phoneNumbers): void
    {
        $phoneNumbers = array_values(array_unique($phoneNumbers));

        if ($phoneNumbers === []) {
            return;
        }

        $contactIds = Contact::query()
            ->where('company_id', $companyId)
            ->whereIn('phone', $phoneNumbers)
            ->pluck('id');

        foreach ($contactIds->chunk(self::ATTACH_BATCH_SIZE) as $chunk) {
            $group->contacts()->syncWithoutDetaching($chunk->values()->all());
        }
    }

    private static function normalizePhoneFromRow(mixed $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        if (is_int($phone) || is_float($phone)) {
            $phone = sprintf('%.0f', $phone);
        } else {
            $phone = trim((string) $phone);
        }

        if ($phone === '') {
            return null;
        }

        if (preg_match('/^[\d.]+[eE][+\-]?\d+$/', $phone)) {
            $phone = sprintf('%.0f', (float) $phone);
        }

        $phone = ltrim($phone, '+');

        return '+'.$phone;
    }
}
