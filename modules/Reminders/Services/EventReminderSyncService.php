<?php

namespace Modules\Reminders\Services;

use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\Remineder;

class EventReminderSyncService
{
    public function sync(Event $event): void
    {
        $this->syncRule(
            $event,
            type: 1,
            campaignId: $event->reminder_before_campaign_id,
            value: $event->reminder_before_value,
            unit: $event->reminder_before_unit,
            suffix: 'before'
        );

        $this->syncRule(
            $event,
            type: 2,
            campaignId: $event->reminder_after_campaign_id,
            value: $event->reminder_after_value,
            unit: $event->reminder_after_unit,
            suffix: 'after'
        );
    }

    private function syncRule(Event $event, int $type, ?int $campaignId, ?int $value, ?string $unit, string $suffix): void
    {
        $name = "{$event->title} — event reminder ({$suffix})";

        $existing = Remineder::query()
            ->where('company_id', $event->company_id)
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->first();

        if (! $campaignId || ! $value || ! $unit) {
            if ($existing) {
                $existing->update(['status' => 2]);
            }

            return;
        }

        $attributes = [
            'company_id' => $event->company_id,
            'event_id' => $event->id,
            'source_id' => null,
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
