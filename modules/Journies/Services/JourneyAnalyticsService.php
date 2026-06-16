<?php

namespace Modules\Journies\Services;

use Illuminate\Support\Facades\DB;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyActivity;
use Modules\Journies\Models\JourneyStage;

class JourneyAnalyticsService
{
    public function summary(?int $journeyId = null): array
    {
        $journeyQuery = Journey::query()->withCount([
            'stages',
        ]);

        if ($journeyId) {
            $journeyQuery->where('id', $journeyId);
        }

        $journeys = $journeyQuery->get();

        $stageStats = JourneyStage::query()
            ->when($journeyId, fn ($q) => $q->where('journey_id', $journeyId))
            ->withCount('contacts')
            ->with('campaign:id,name')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (JourneyStage $stage) => [
                'id' => $stage->id,
                'journey_id' => $stage->journey_id,
                'name' => $stage->name,
                'contacts_count' => $stage->contacts_count,
                'campaign_name' => $stage->campaign?->name,
                'order' => $stage->order,
            ]);

        $totalContacts = (int) DB::table('journey_stage_contacts')
            ->when($journeyId, function ($query) use ($journeyId) {
                $query->whereIn('stage_id', function ($sub) use ($journeyId) {
                    $sub->select('id')->from('journey_stages')->where('journey_id', $journeyId);
                });
            })
            ->distinct()
            ->count('contact_id');

        $recentMoves = JourneyActivity::query()
            ->with(['contact:id,name', 'stage:id,name', 'journey:id,name', 'user:id,name'])
            ->when($journeyId, fn ($q) => $q->where('journey_id', $journeyId))
            ->whereIn('action', [JourneyActivity::ACTION_MOVED, JourneyActivity::ACTION_ADDED])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (JourneyActivity $activity) => [
                'id' => $activity->id,
                'contact_name' => $activity->contact?->name,
                'journey_name' => $activity->journey?->name,
                'stage_name' => $activity->stage?->name,
                'user_name' => $activity->user?->name,
                'source' => $activity->source,
                'campaign_status' => $activity->campaign_status,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        $campaignStats = JourneyActivity::query()
            ->when($journeyId, fn ($q) => $q->where('journey_id', $journeyId))
            ->whereNotNull('campaign_id')
            ->select('campaign_status', DB::raw('count(*) as total'))
            ->groupBy('campaign_status')
            ->pluck('total', 'campaign_status');

        return [
            'journeys_count' => $journeys->count(),
            'total_contacts' => $totalContacts,
            'stages' => $stageStats,
            'recent_activity' => $recentMoves,
            'campaign_stats' => $campaignStats,
        ];
    }

    public function conversionRates(int $journeyId): array
    {
        $stages = JourneyStage::query()
            ->where('journey_id', $journeyId)
            ->orderBy('order')
            ->orderBy('id')
            ->withCount('contacts')
            ->get();

        $rates = [];
        $previousCount = null;

        foreach ($stages as $stage) {
            $rate = null;

            if ($previousCount !== null && $previousCount > 0) {
                $rate = round(($stage->contacts_count / $previousCount) * 100, 1);
            }

            $rates[] = [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'contacts_count' => $stage->contacts_count,
                'conversion_from_previous' => $rate,
            ];

            $previousCount = $stage->contacts_count;
        }

        return $rates;
    }
}
