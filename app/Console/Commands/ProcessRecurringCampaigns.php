<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Wpbox\Models\Campaign;

class ProcessRecurringCampaigns extends Command
{
    protected $signature = 'campaigns:process-recurring';

    protected $description = 'Launch recurring campaigns when their next run time is due';

    public function handle(): int
    {
        $due = Campaign::withoutGlobalScopes()
            ->whereNotNull('recurrence_rule')
            ->whereNotNull('recurrence_next_at')
            ->where('recurrence_next_at', '<=', now())
            ->where('is_active', true)
            ->whereIn('status', [Campaign::STATUS_SCHEDULED, Campaign::STATUS_COMPLETED])
            ->get();

        $processed = 0;

        foreach ($due as $campaign) {
            $clone = $campaign->cloneAsDraft($campaign->name.' — '.now()->format('Y-m-d H:i'));
            $clone->status = Campaign::STATUS_SCHEDULED;
            $clone->recurrence_rule = null;
            $clone->recurrence_next_at = null;
            $clone->save();

            $request = new \Illuminate\Http\Request();
            $request->merge(['send_now' => 'on']);

            $clone->makeMessages($request);
            $clone->update([
                'status' => Campaign::STATUS_SENDING,
                'launched_at' => now(),
            ]);

            $campaign->recurrence_next_at = $this->nextOccurrence($campaign);

            if ($campaign->recurrence_next_at === null) {
                $campaign->recurrence_rule = null;
            }

            $campaign->save();
            $processed++;
        }

        $this->info("Processed {$processed} recurring campaign(s).");

        return self::SUCCESS;
    }

    private function nextOccurrence(Campaign $campaign): ?Carbon
    {
        $rule = $campaign->recurrence_rule ?? [];
        $interval = $rule['interval'] ?? 'weekly';
        $count = (int) ($rule['count'] ?? 0);
        $current = (int) ($rule['runs_completed'] ?? 0);

        if ($count > 0 && $current >= $count) {
            return null;
        }

        $base = $campaign->recurrence_next_at ?? now();

        $next = match ($interval) {
            'daily' => Carbon::parse($base)->addDay(),
            'monthly' => Carbon::parse($base)->addMonth(),
            default => Carbon::parse($base)->addWeek(),
        };

        $rule['runs_completed'] = $current + 1;
        $campaign->recurrence_rule = $rule;

        return $next;
    }
}
