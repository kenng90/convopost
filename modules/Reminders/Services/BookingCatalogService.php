<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Modules\Reminders\Models\Source;

class BookingCatalogService
{
    /**
     * @return array<int, array{id: int, name: string, duration_options: array<int, int>, default_duration_minutes: int, timezone: string}>
     */
    public function bookableServicesForCompany(Company $company): array
    {
        return Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Source $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'duration_options' => $source->durationOptions(),
                'default_duration_minutes' => (int) ($source->default_duration_minutes ?: 30),
                'timezone' => $source->timezone ?: config('app.timezone', 'UTC'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, title: string}>
     */
    public function bookableServicesAsFlowOptions(Company $company): array
    {
        return collect($this->bookableServicesForCompany($company))
            ->map(fn (array $service) => [
                'id' => (string) $service['name'],
                'title' => (string) $service['name'],
            ])
            ->values()
            ->all();
    }
}
