<?php

namespace Modules\Reminders\Services;

use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Source;

class SourceReminderSyncService
{
    public function sync(Source $source): void
    {
        $this->syncRule(
            $source,
            type: 1,
            campaignId: $source->reminder_before_campaign_id,
            value: $source->reminder_before_value,
            unit: $source->reminder_before_unit,
            suffix: 'before'
        );

        $this->syncRule(
            $source,
            type: 2,
            campaignId: $source->reminder_after_campaign_id,
            value: $source->reminder_after_value,
            unit: $source->reminder_after_unit,
            suffix: 'after'
        );
    }

    private function syncRule(Source $source, int $type, ?int $campaignId, ?int $value, ?string $unit, string $suffix): void
    {
        $name = "{$source->name} — client reminder ({$suffix})";

        $existing = Remineder::query()
            ->where('company_id', $source->company_id)
            ->where('source_id', $source->id)
            ->where('type', $type)
            ->first();

        if (! $campaignId || ! $value || ! $unit) {
            if ($existing) {
                $existing->update(['status' => 2]);
            }

            return;
        }

        $attributes = [
            'company_id' => $source->company_id,
            'source_id' => $source->id,
            'name' => $name,
            'type' => $type,
            'time' => $value,
            'time_type' => $unit,
            'campaign_id' => $campaignId,
            'status' => 1,
            'is_service_managed' => true,
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            Remineder::create($attributes);
        }
    }
}
