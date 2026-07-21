<?php

namespace App\Services\Catalog;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Support\WorkingHours;

class CatalogBookableImportService
{
    public function supportsMode(?string $mode): bool
    {
        return in_array($mode, [CatalogMode::LISTING, CatalogMode::SERVICE], true);
    }

    /**
     * @param  list<array<string, mixed>>  $transformedItems
     * @param  list<array<string, mixed>>  $existingItems
     * @return array{
     *     strategy: string,
     *     catalog_mode: string,
     *     vertical: ?string,
     *     shared: ?array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     sources: list<array{id: int, name: string}>,
     *     staff: list<array{id: int, name: string}>,
     *     defaults: array<string, mixed>
     * }
     */
    public function buildPlan(
        Company $company,
        array $transformedItems,
        array $existingItems = [],
        ?string $catalogMode = null,
        ?string $vertical = null,
    ): array {
        $catalogMode = $catalogMode ?: CatalogMode::SERVICE;
        $sources = $this->bookableSourcesForCompany($company);
        $base = [
            'catalog_mode' => $catalogMode,
            'vertical' => $vertical,
            'sources' => $sources->map(fn (Source $source) => [
                'id' => (int) $source->id,
                'name' => (string) $source->name,
            ])->values()->all(),
            'staff' => $this->staffOptionsForCompany($company),
            'defaults' => [
                'default_duration_minutes' => 30,
                'timezone' => config('app.timezone', 'UTC'),
                'working_hours' => WorkingHours::default(),
                'staff_ids' => [],
            ],
        ];

        if ($catalogMode === CatalogMode::LISTING) {
            return array_merge($base, $this->buildSharedListingPlan($sources, $transformedItems, $existingItems, $vertical));
        }

        $sourcesById = $sources->keyBy('id');
        $sourcesByName = $sources->keyBy(fn (Source $source) => $this->normalizeName($source->name));
        $existingById = collect($existingItems)
            ->filter(fn ($item) => filled($item['id'] ?? null))
            ->keyBy(fn ($item) => (string) $item['id']);

        $rows = [];
        foreach ($transformedItems as $item) {
            $rows[] = $this->suggestRow($item, $sourcesById, $sourcesByName, $existingById);
        }

        return array_merge($base, [
            'strategy' => 'per_item',
            'shared' => null,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $transformedItems
     * @param  list<array<string, mixed>>  $decisions
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>|null  $sharedDecision
     * @return array{items: list<array<string, mixed>>, created: int, linked: int, skipped: int, strategy: string}
     */
    public function applyPlan(
        Company $company,
        array $transformedItems,
        array $decisions = [],
        array $defaults = [],
        ?string $catalogMode = null,
        ?array $sharedDecision = null,
        ?string $vertical = null,
    ): array {
        $catalogMode = $catalogMode ?: CatalogMode::SERVICE;

        if ($catalogMode === CatalogMode::LISTING) {
            return $this->applySharedListingPlan(
                $company,
                $transformedItems,
                $sharedDecision ?? [],
                $defaults,
                $vertical
            );
        }

        $defaults = $this->normalizeDefaults($defaults);
        $staffIds = $this->validatedStaffIds($company, $defaults['staff_ids']);
        $sources = $this->bookableSourcesForCompany($company);
        $sourcesById = $sources->keyBy('id');
        $decisionsByItemId = collect($decisions)
            ->filter(fn ($decision) => filled($decision['item_id'] ?? null))
            ->keyBy(fn ($decision) => (string) $decision['item_id']);

        $created = 0;
        $linked = 0;
        $skipped = 0;
        $result = [];

        foreach ($transformedItems as $item) {
            $itemId = (string) ($item['id'] ?? '');
            $decision = $decisionsByItemId->get($itemId, [
                'item_id' => $itemId,
                'action' => 'create',
                'name' => $item['title'] ?? $itemId,
            ]);

            $action = (string) ($decision['action'] ?? 'skip');
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

            if ($action === 'skip') {
                unset($metadata['booking_source_id'], $metadata['booking_source_name']);
                $item['metadata'] = $metadata;
                $result[] = $item;
                $skipped++;

                continue;
            }

            if ($action === 'link') {
                $sourceId = (int) ($decision['source_id'] ?? 0);
                if ($sourceId <= 0 || ! $sourcesById->has($sourceId)) {
                    unset($metadata['booking_source_id'], $metadata['booking_source_name']);
                    $item['metadata'] = $metadata;
                    $result[] = $item;
                    $skipped++;

                    continue;
                }

                $metadata['booking_source_id'] = $sourceId;
                unset($metadata['booking_source_name']);
                $item['metadata'] = $metadata;
                $result[] = $item;
                $linked++;

                continue;
            }

            $name = trim((string) ($decision['name'] ?? $item['title'] ?? $itemId));
            if ($name === '') {
                $name = $itemId !== '' ? $itemId : 'Service';
            }

            $duration = (int) ($decision['duration_minutes'] ?? 0);
            if ($duration < 5) {
                $duration = $this->parseDurationMinutes(
                    $metadata['duration'] ?? $item['duration'] ?? null,
                    $defaults['default_duration_minutes']
                );
            }

            $source = $this->createBookableSource($company, $name, $duration, $defaults, $staffIds);
            $sourcesById->put($source->id, $source);

            $metadata['booking_source_id'] = (int) $source->id;
            unset($metadata['booking_source_name']);
            $item['metadata'] = $metadata;
            $result[] = $item;
            $created++;
        }

        return [
            'items' => $result,
            'created' => $created,
            'linked' => $linked,
            'skipped' => $skipped,
            'strategy' => 'per_item',
        ];
    }

    public function sharedServiceNameForVertical(?string $vertical): string
    {
        if (! is_string($vertical) || $vertical === '') {
            return 'Listings';
        }

        $config = config('catalog-templates.verticals.'.$vertical, []);
        $label = trim((string) ($config['label'] ?? ''));

        return $label !== '' ? $label : 'Listings';
    }

    /**
     * @param  Collection<int, Source>  $sources
     * @param  list<array<string, mixed>>  $transformedItems
     * @param  list<array<string, mixed>>  $existingItems
     * @return array{strategy: string, shared: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    private function buildSharedListingPlan(
        Collection $sources,
        array $transformedItems,
        array $existingItems,
        ?string $vertical,
    ): array {
        $serviceName = $this->sharedServiceNameForVertical($vertical);
        $sourcesById = $sources->keyBy('id');
        $sourcesByName = $sources->keyBy(fn (Source $source) => $this->normalizeName($source->name));

        $matched = $sourcesByName->get($this->normalizeName($serviceName));
        $action = $matched ? 'link' : 'create';
        $sourceId = $matched ? (int) $matched->id : null;

        // Prefer an existing shared link already used by catalog items.
        $existingSourceIds = collect($existingItems)
            ->map(function ($item) {
                $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

                return (int) ($metadata['booking_source_id'] ?? 0);
            })
            ->filter(fn ($id) => $id > 0 && $sourcesById->has($id))
            ->countBy()
            ->sortDesc();

        if ($existingSourceIds->isNotEmpty()) {
            $topSourceId = (int) $existingSourceIds->keys()->first();
            $topSource = $sourcesById->get($topSourceId);
            if ($topSource) {
                $action = 'link';
                $sourceId = $topSourceId;
                $serviceName = (string) $topSource->name;
            }
        }

        return [
            'strategy' => 'shared',
            'shared' => [
                'action' => $action,
                'name' => $serviceName,
                'source_id' => $sourceId,
                'duration_minutes' => 30,
                'item_count' => count($transformedItems),
            ],
            'rows' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $transformedItems
     * @param  array<string, mixed>  $sharedDecision
     * @param  array<string, mixed>  $defaults
     * @return array{items: list<array<string, mixed>>, created: int, linked: int, skipped: int, strategy: string}
     */
    private function applySharedListingPlan(
        Company $company,
        array $transformedItems,
        array $sharedDecision,
        array $defaults,
        ?string $vertical,
    ): array {
        $defaults = $this->normalizeDefaults($defaults);
        $staffIds = $this->validatedStaffIds($company, $defaults['staff_ids']);
        $sources = $this->bookableSourcesForCompany($company);
        $sourcesById = $sources->keyBy('id');

        $action = (string) ($sharedDecision['action'] ?? 'create');
        $name = trim((string) ($sharedDecision['name'] ?? $this->sharedServiceNameForVertical($vertical)));
        if ($name === '') {
            $name = $this->sharedServiceNameForVertical($vertical);
        }

        if ($action === 'skip' || $transformedItems === []) {
            $result = [];
            foreach ($transformedItems as $item) {
                $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
                unset($metadata['booking_source_id'], $metadata['booking_source_name']);
                $item['metadata'] = $metadata;
                $result[] = $item;
            }

            return [
                'items' => $result,
                'created' => 0,
                'linked' => 0,
                'skipped' => count($result),
                'strategy' => 'shared',
            ];
        }

        $sourceId = 0;
        $created = 0;

        if ($action === 'link') {
            $sourceId = (int) ($sharedDecision['source_id'] ?? 0);
            if ($sourceId <= 0 || ! $sourcesById->has($sourceId)) {
                $result = [];
                foreach ($transformedItems as $item) {
                    $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
                    unset($metadata['booking_source_id'], $metadata['booking_source_name']);
                    $item['metadata'] = $metadata;
                    $result[] = $item;
                }

                return [
                    'items' => $result,
                    'created' => 0,
                    'linked' => 0,
                    'skipped' => count($result),
                    'strategy' => 'shared',
                ];
            }
        } else {
            $duration = (int) ($sharedDecision['duration_minutes'] ?? 0);
            if ($duration < 5) {
                $duration = $defaults['default_duration_minutes'];
            }

            $source = $this->createBookableSource($company, $name, $duration, $defaults, $staffIds);
            $sourceId = (int) $source->id;
            $created = 1;
        }

        $result = [];
        foreach ($transformedItems as $item) {
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $metadata['booking_source_id'] = $sourceId;
            unset($metadata['booking_source_name']);
            $item['metadata'] = $metadata;
            $result[] = $item;
        }

        return [
            'items' => $result,
            'created' => $created,
            'linked' => count($result),
            'skipped' => 0,
            'strategy' => 'shared',
        ];
    }

    public function parseDurationMinutes(mixed $value, int $fallback = 30): int
    {
        if (is_int($value) || is_float($value)) {
            $minutes = (int) round((float) $value);

            return $minutes >= 5 ? $minutes : $fallback;
        }

        if (! is_string($value)) {
            return $fallback;
        }

        $raw = trim(strtolower($value));
        if ($raw === '') {
            return $fallback;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*h(?:ours?)?(?:\s*(\d+)\s*m(?:in(?:utes?)?)?)?$/', $raw, $matches)) {
            $minutes = (int) round(((float) $matches[1]) * 60);
            if (! empty($matches[2])) {
                $minutes += (int) $matches[2];
            }

            return $minutes >= 5 ? $minutes : $fallback;
        }

        if (preg_match('/^(\d+)\s*m(?:in(?:utes?)?)?$/', $raw, $matches)) {
            $minutes = (int) $matches[1];

            return $minutes >= 5 ? $minutes : $fallback;
        }

        if (preg_match('/^(\d+)$/', $raw, $matches)) {
            $minutes = (int) $matches[1];

            return $minutes >= 5 ? $minutes : $fallback;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array{
     *     default_duration_minutes: int,
     *     timezone: string,
     *     working_hours: array<string, array{enabled: bool, start: string, end: string}>,
     *     staff_ids: list<int>
     * }
     */
    public function normalizeDefaults(array $defaults): array
    {
        $duration = (int) ($defaults['default_duration_minutes'] ?? 30);
        if ($duration < 5) {
            $duration = 30;
        }

        $timezone = trim((string) ($defaults['timezone'] ?? config('app.timezone', 'UTC')));
        if ($timezone === '') {
            $timezone = (string) config('app.timezone', 'UTC');
        }

        $staffIds = collect($defaults['staff_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'default_duration_minutes' => $duration,
            'timezone' => $timezone,
            'working_hours' => WorkingHours::normalize(
                is_array($defaults['working_hours'] ?? null) ? $defaults['working_hours'] : null
            ),
            'staff_ids' => $staffIds,
        ];
    }

    /**
     * @param  Collection<int, Source>  $sourcesById
     * @param  Collection<string, Source>  $sourcesByName
     * @param  Collection<string, array<string, mixed>>  $existingById
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function suggestRow(
        array $item,
        Collection $sourcesById,
        Collection $sourcesByName,
        Collection $existingById,
    ): array {
        $itemId = (string) ($item['id'] ?? '');
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $title = trim((string) ($item['title'] ?? ''));
        $excelName = trim((string) ($metadata['booking_source_name'] ?? $item['booking_source_name'] ?? ''));
        $suggestedName = $excelName !== '' ? $excelName : ($title !== '' ? $title : $itemId);
        $duration = $this->parseDurationMinutes($metadata['duration'] ?? $item['duration'] ?? null);

        $excelSourceId = (int) ($metadata['booking_source_id'] ?? $item['booking_source_id'] ?? 0);
        if ($excelSourceId > 0 && $sourcesById->has($excelSourceId)) {
            return [
                'item_id' => $itemId,
                'title' => $title,
                'action' => 'link',
                'name' => $suggestedName,
                'source_id' => $excelSourceId,
                'duration_minutes' => $duration,
            ];
        }

        foreach ([$excelName, $title] as $candidate) {
            $normalized = $this->normalizeName($candidate);
            if ($normalized !== '' && $sourcesByName->has($normalized)) {
                $matched = $sourcesByName->get($normalized);

                return [
                    'item_id' => $itemId,
                    'title' => $title,
                    'action' => 'link',
                    'name' => $suggestedName,
                    'source_id' => (int) $matched->id,
                    'duration_minutes' => $duration,
                ];
            }
        }

        $existing = $existingById->get($itemId);
        if (is_array($existing)) {
            $existingMeta = is_array($existing['metadata'] ?? null) ? $existing['metadata'] : [];
            $existingSourceId = (int) ($existingMeta['booking_source_id'] ?? 0);
            if ($existingSourceId > 0 && $sourcesById->has($existingSourceId)) {
                return [
                    'item_id' => $itemId,
                    'title' => $title,
                    'action' => 'link',
                    'name' => $suggestedName,
                    'source_id' => $existingSourceId,
                    'duration_minutes' => $duration,
                ];
            }
        }

        return [
            'item_id' => $itemId,
            'title' => $title,
            'action' => 'create',
            'name' => $suggestedName,
            'source_id' => null,
            'duration_minutes' => $duration,
        ];
    }

    /**
     * @param  array{
     *     default_duration_minutes: int,
     *     timezone: string,
     *     working_hours: array<string, array{enabled: bool, start: string, end: string}>,
     *     staff_ids: list<int>
     * }  $defaults
     * @param  list<int>  $staffIds
     */
    private function createBookableSource(
        Company $company,
        string $name,
        int $duration,
        array $defaults,
        array $staffIds,
    ): Source {
        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $name,
            'is_bookable' => true,
            'default_duration_minutes' => $duration,
            'duration_options' => [$duration],
            'buffer_minutes' => 0,
            'timezone' => $defaults['timezone'],
            'min_notice_hours' => 1,
            'max_advance_days' => 60,
            'staff_assignment_mode' => Source::ASSIGNMENT_CUSTOMER_CHOICE,
            'working_hours' => $defaults['working_hours'],
            'payment_required' => false,
            'payment_currency' => 'KES',
            'sort_order' => 0,
        ]);

        $this->attachStaff($source, $staffIds);

        return $source;
    }

    /**
     * @param  list<int>  $staffIds
     */
    private function attachStaff(Source $source, array $staffIds): void
    {
        foreach ($staffIds as $staffId) {
            SourceStaff::create([
                'source_id' => $source->id,
                'appointment_staff_id' => $staffId,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return Collection<int, Source>
     */
    private function bookableSourcesForCompany(Company $company): Collection
    {
        return Source::queryForCompany($company->id)
            ->where('is_bookable', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function staffOptionsForCompany(Company $company): array
    {
        return AppointmentStaff::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AppointmentStaff $staff) => [
                'id' => (int) $staff->id,
                'name' => (string) $staff->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $staffIds
     * @return list<int>
     */
    private function validatedStaffIds(Company $company, array $staffIds): array
    {
        if ($staffIds === []) {
            return [];
        }

        return AppointmentStaff::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('id', $staffIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function normalizeName(?string $name): string
    {
        return strtolower(trim((string) $name));
    }
}
