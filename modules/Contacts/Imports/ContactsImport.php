<?php

namespace Modules\Contacts\Imports;

use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithGroupedHeadingRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Modules\Contacts\Jobs\AssignContactImportGroupJob;
use Modules\Contacts\Models\Contact;
use Modules\Contacts\Models\ContactImport;
use Modules\Contacts\Models\Field;

class ContactsImport implements ToModel, WithChunkReading, WithEvents, WithGroupedHeadingRow, WithHeadingRow
{
    use RegistersEventListeners;

    public function __construct(public ?int $contactImportId = null)
    {
    }

    public function chunkSize(): int
    {
        return 150;
    }

    /**
     * @return Contact|null
     */
    public function model(array $row)
    {
        $phone = $this->normalizePhone($this->resolveCellValue($row['phone'] ?? null));

        if ($phone === null) {
            $this->trackStat('skipped_count');
            $this->trackProgress();

            return null;
        }

        $name = $this->resolveCellValue($row['name'] ?? null);

        $keys = array_keys($row);
        $keysForFields = [];
        foreach ($keys as $key => $value) {
            $keysForFields[$key] = $this->getOrMakeField($value);
        }

        $prevContact = Contact::where('phone', $phone)->first();
        if ($prevContact) {
            $contact = $prevContact;
            $contact->fields()->detach();

            if ($name !== null && trim((string) $name) !== '') {
                $contact->name = trim((string) $name);
            }

            $this->trackStat('updated_count');
        } else {
            $contact = new Contact([
                'name' => $this->resolveName($name),
                'phone' => $phone,
            ]);
            $contact->save();
            $this->trackStat('created_count');
        }

        if ($avatar = $this->resolveCellValue($row['avatar'] ?? null)) {
            $contact->avatar = $avatar;
        }

        foreach ($keysForFields as $key => $fieldID) {
            $cellValue = $this->resolveCellValue($row[$keys[$key]] ?? null);

            if ($fieldID != 0 && $cellValue !== null && trim((string) $cellValue) !== '') {
                $contact->fields()->attach($fieldID, ['value' => $cellValue]);
            }
        }

        $contact->update();

        $this->trackProgress();

        return $contact;
    }

    public function beforeImport(BeforeImport $event): void
    {
        if (! $this->contactImportId) {
            return;
        }

        $contactImport = ContactImport::find($this->contactImportId);

        if (! $contactImport) {
            return;
        }

        session(['company_id' => $contactImport->company_id]);

        $totalRows = array_sum($event->getReader()->getTotalRows());
        $totalRows = max(0, $totalRows - 1);

        $contactImport->update([
            'status' => ContactImport::STATUS_PROCESSING,
            'started_at' => $contactImport->started_at ?? now(),
            'total_rows' => $totalRows,
        ]);
    }

    public function afterImport(AfterImport $event): void
    {
        if (! $this->contactImportId) {
            return;
        }

        $contactImport = ContactImport::find($this->contactImportId);

        if (! $contactImport) {
            return;
        }

        $contactImport->update([
            'status' => ContactImport::STATUS_COMPLETED,
            'finished_at' => now(),
            'processed_rows' => max($contactImport->processed_rows, $contactImport->total_rows),
        ]);

        if ($contactImport->group_id) {
            AssignContactImportGroupJob::dispatch($contactImport);
        }
    }

    public function importFailed(ImportFailed $event): void
    {
        if (! $this->contactImportId) {
            return;
        }

        ContactImport::where('id', $this->contactImportId)->update([
            'status' => ContactImport::STATUS_FAILED,
            'error_message' => $event->getException()->getMessage(),
            'finished_at' => now(),
        ]);
    }

    private function trackStat(string $stat): void
    {
        if ($this->contactImportId) {
            ContactImport::where('id', $this->contactImportId)->increment($stat);
        }
    }

    private function trackProgress(): void
    {
        if (! $this->contactImportId) {
            return;
        }

        ContactImport::where('id', $this->contactImportId)->increment('processed_rows');
    }

    private function resolveCellValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            if (is_string($item) && trim($item) === '') {
                continue;
            }

            return $item;
        }

        return null;
    }

    private function normalizePhone(mixed $phone): ?string
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

    private function resolveName(mixed $name): string
    {
        $name = trim((string) ($name ?? ''));

        return $name !== '' ? $name : __('Unknown');
    }

    private function getOrMakeField($field_name)
    {
        if ($field_name == 'name' || $field_name == 'phone' || $field_name == 'avatar') {
            return 0;
        }

        $field = Field::where('name', $field_name)->first();

        if (! $field) {
            $field = Field::create([
                'name' => $field_name,
                'type' => 'text',
            ]);
            $field->save();
        }

        return $field->id;
    }
}
