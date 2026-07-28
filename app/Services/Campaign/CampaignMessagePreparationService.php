<?php

namespace App\Services\Campaign;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Wpbox\Models\Campaign;

class CampaignMessagePreparationService
{
    public function __construct(
        private readonly CampaignAudienceResolver $audience,
        private readonly CampaignFileParser $fileParser,
        private readonly CampaignContactUpsertService $contacts,
        private readonly CampaignRecipientGuard $guard,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function prepare(Campaign $campaign, array $payload): void
    {
        $campaign->refresh();
        $company = Company::findOrFail($campaign->company_id);

        $campaign->update([
            'status' => Campaign::STATUS_PREPARING,
            'messages_prepared_count' => 0,
            'preparation_error' => null,
        ]);

        try {
            $prepared = match ($campaign->broadcast_type) {
                'file' => $this->prepareFromFile($campaign, $company, $payload),
                'quick' => $this->prepareFromQuickPhones($campaign, $company, $payload),
                default => $this->prepareFromAudience($campaign, $company, $payload),
            };

            if ($prepared === 0) {
                throw new \RuntimeException(__('No messages could be prepared for this campaign.'));
            }

            $campaign->update([
                'send_to' => $prepared,
                'total_contacts' => $prepared,
                'messages_prepared_count' => $prepared,
                'status' => Campaign::STATUS_SENDING,
                'launched_at' => now(),
                'is_active' => true,
                'preparation_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Campaign message preparation failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            $campaign->update([
                'status' => Campaign::STATUS_PREPARATION_FAILED,
                'preparation_error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->cleanupStoredFile($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function prepareFromAudience(Campaign $campaign, Company $company, array $payload): int
    {
        $request = $this->buildRequestFromPayload($payload);
        $campaign->warmTemplateCache();

        $query = $this->audience->subscribedQuery($company, [
            'group_id' => $campaign->group_id,
            'segment_id' => $campaign->segment_id,
            'contact_id' => $campaign->contact_id,
        ]);

        return $this->prepareFromContactQuery($campaign, $request, $query, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function prepareFromQuickPhones(Campaign $campaign, Company $company, array $payload): int
    {
        $phones = collect(preg_split('/[\n,]+/', (string) ($payload['quick_phones'] ?? '')))
            ->map(fn ($phone) => $this->fileParser->normalizePhoneFromCell($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->guard->assertWithinLimit(count($phones), $campaign);

        $request = $this->buildRequestFromPayload($payload);
        $campaign->warmTemplateCache();
        $prepared = 0;

        foreach (array_chunk($phones, $this->guard->preparationChunkSize()) as $phoneChunk) {
            $contactMap = $this->contacts->upsertPhones($company, $phoneChunk);
            $messages = [];

            foreach ($phoneChunk as $phone) {
                $contact = $contactMap[$phone] ?? null;

                if (! $contact) {
                    continue;
                }

                $messageData = $campaign->buildMessageDataForContact($contact, $request);

                if ($messageData !== null) {
                    $messages[] = $messageData;
                }
            }

            $prepared += $this->flushMessages($campaign, $messages);
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function prepareFromFile(Campaign $campaign, Company $company, array $payload): int
    {
        $path = (string) ($payload['contact_file_path'] ?? '');

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            throw new \RuntimeException(__('Campaign recipient file is missing.'));
        }

        $absolutePath = Storage::disk('local')->path($path);
        $extension = (string) ($payload['contact_file_extension'] ?? 'csv');
        $headers = $this->fileParser->readHeadersFromPath($absolutePath, $extension);
        $recipientColumn = (string) ($payload['recipient_column'] ?? $payload['phone_column'] ?? '');
        $columnIndex = $this->fileParser->resolveColumnIndex($headers, $recipientColumn);

        if ($columnIndex === false) {
            throw new \RuntimeException(__('Selected recipient column not found in file.'));
        }

        $validCount = $this->fileParser->countValidRecipientRowsFromPath(
            $absolutePath,
            $extension,
            $headers,
            $columnIndex,
            $campaign->resolvedChannel()
        );

        $this->guard->assertWithinLimit($validCount, $campaign);

        $request = $this->buildRequestFromPayload($payload);
        $campaign->warmTemplateCache();
        $fileColumnMap = $payload['file_column_map'] ?? [];
        $staticParamValues = $payload['paramvalues'] ?? [];
        $channel = $campaign->resolvedChannel();
        $prepared = 0;
        $demoLimit = config('settings.is_demo', false) ? 5 : null;
        $pendingMessages = [];

        foreach ($this->fileParser->iterateRowsFromPath($absolutePath, $extension, $headers) as $row) {
            if ($demoLimit !== null && $prepared >= $demoLimit) {
                break;
            }

            if (empty(array_filter($row))) {
                continue;
            }

            while (count($row) < count($headers)) {
                $row[] = '';
            }

            $cell = $row[$columnIndex] ?? null;

            if ($channel === Campaign::CHANNEL_EMAIL) {
                $email = $this->fileParser->normalizeEmailFromCell($cell);

                if ($email === null) {
                    continue;
                }

                $contact = $this->contacts->upsertEmail($company, $email);
            } else {
                $phone = $this->fileParser->normalizePhoneFromCell($cell);

                if ($phone === null) {
                    continue;
                }

                $contact = $this->contacts->upsertPhone($company, $phone);
            }

            $perRowParams = $this->applyFileColumnMapToParamValues($staticParamValues, $fileColumnMap, $headers, $row);
            $messageData = $campaign->buildMessageDataForContact($contact, $request, $perRowParams);

            if ($messageData === null) {
                continue;
            }

            $pendingMessages[] = $messageData;

            if (count($pendingMessages) >= $this->guard->insertChunkSize()) {
                $prepared += $this->flushMessages($campaign, $pendingMessages);
                $pendingMessages = [];
            }
        }

        if ($pendingMessages !== []) {
            $prepared += $this->flushMessages($campaign, $pendingMessages);
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function prepareFromContactQuery(Campaign $campaign, Request $request, Builder $query, array $payload): int
    {
        $count = (clone $query)->count();
        $this->guard->assertWithinLimit($count, $campaign);

        $prepared = 0;
        $chunkSize = $this->guard->preparationChunkSize();
        $demoLimit = config('settings.is_demo', false) ? 5 : null;

        $query->with(['country', 'fields'])->orderBy('contacts.id');

        $query->chunkById($chunkSize, function ($contacts) use ($campaign, $request, &$prepared, $demoLimit) {
            $messages = [];

            foreach ($contacts as $contact) {
                if ($demoLimit !== null && $prepared >= $demoLimit) {
                    return false;
                }

                $messageData = $campaign->buildMessageDataForContact($contact, $request);

                if ($messageData !== null) {
                    $messages[] = $messageData;
                }
            }

            $prepared += $this->flushMessages($campaign, $messages);

            return $demoLimit === null || $prepared < $demoLimit;
        }, 'contacts.id', 'id');

        return $prepared;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function flushMessages(Campaign $campaign, array $messages): int
    {
        if ($messages === []) {
            return 0;
        }

        $campaign->insertCampaignMessages($messages);
        $campaign->increment('messages_prepared_count', count($messages));

        return count($messages);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildRequestFromPayload(array $payload): Request
    {
        $request = new Request();
        $request->merge([
            'paramvalues' => $payload['paramvalues'] ?? [],
            'parammatch' => $payload['parammatch'] ?? [],
            'send_now' => ($payload['send_now'] ?? true) ? 'on' : null,
            'send_time' => $payload['send_time'] ?? null,
        ]);

        return $request;
    }

    /**
     * @param  array<string, mixed>  $staticParamValues
     * @param  array<string, array<string, string>>  $fileColumnMap
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyFileColumnMapToParamValues(array $staticParamValues, array $fileColumnMap, array $headers, array $row): array
    {
        $perRowParams = json_decode(json_encode($staticParamValues), true) ?? [];

        foreach (['body', 'header'] as $section) {
            if (! isset($fileColumnMap[$section])) {
                continue;
            }

            foreach ($fileColumnMap[$section] as $variableId => $colName) {
                if (empty($colName)) {
                    continue;
                }

                $colIdx = $this->fileParser->resolveColumnIndex($headers, $colName);

                if ($colIdx === false) {
                    continue;
                }

                $cellValue = $row[$colIdx] ?? '';

                if (is_int($cellValue) || is_float($cellValue)) {
                    $cellValue = number_format((float) $cellValue, 0, '', '');
                }

                $perRowParams[$section][$variableId] = trim((string) $cellValue);
            }
        }

        return $perRowParams;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cleanupStoredFile(array $payload): void
    {
        $path = (string) ($payload['contact_file_path'] ?? '');

        if ($path !== '') {
            Storage::disk('local')->delete($path);
        }
    }
}
