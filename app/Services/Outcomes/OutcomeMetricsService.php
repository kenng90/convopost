<?php

namespace App\Services\Outcomes;

use App\Models\CatalogCartSession;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Reminders\Models\Reservation;

class OutcomeMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function forCompany(Company $company): array
    {
        $billing = app(OutcomeSkuBiller::class)->summaryForCompany($company);

        return [
            'cart_recovery' => array_merge($this->cartRecovery($company), [
                'sku_billing' => $billing['cart_recovery'] ?? ['count' => 0, 'credits' => 0, 'revenue' => 0],
            ]),
            'booking_convert' => array_merge($this->bookingConvert($company), [
                'sku_billing' => $billing['booking_convert'] ?? ['count' => 0, 'credits' => 0, 'revenue' => 0],
            ]),
            'lead_to_cash' => array_merge($this->leadToCash($company), [
                'sku_billing' => $billing['lead_to_cash'] ?? ['count' => 0, 'credits' => 0, 'revenue' => 0],
            ]),
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    public function cartRecovery(Company $company): array
    {
        $abandoned = CatalogCartSession::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('abandoned_at')
            ->whereNull('converted_at')
            ->count();

        $recovered = CatalogCartSession::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('converted_at')
            ->whereNotNull('abandoned_at')
            ->count();

        $stageCounts = $this->stageCounts($company, 'outcome_cart_recovery_journey_id');

        $recoveredRevenue = 0.0;
        if (class_exists(Invoice::class)) {
            $recoveredRevenue = (float) Invoice::where('company_id', $company->id)
                ->where('status', 'paid')
                ->where('notes->source', 'cart_recovery')
                ->sum('amount');
        }

        return [
            'abandoned' => $abandoned,
            'recovered' => $recovered + (int) ($stageCounts['Recovered'] ?? 0),
            'lost' => (int) ($stageCounts['Lost'] ?? 0),
            'nurturing' => (int) ($stageCounts['Nurturing'] ?? 0),
            'recovered_revenue' => $recoveredRevenue,
            'recovered_revenue_formatted' => $company->currency.' '.number_format($recoveredRevenue, 0),
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    public function bookingConvert(Company $company): array
    {
        $booked = 0;
        $attended = 0;
        $noShows = 0;

        if (class_exists(Reservation::class) && DB::getSchemaBuilder()->hasTable('rem_reservations')) {
            $booked = Reservation::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereNull('cancelled_at')
                ->where('status', '!=', 2)
                ->count();

            $attended = Reservation::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereNull('cancelled_at')
                ->where('status', '!=', 2)
                ->where('end_date', '<', now())
                ->where('start_date', '>=', now()->subDays(30))
                ->count();

            $noShows = (int) ($this->stageCounts($company, 'outcome_booking_convert_journey_id')['No-show'] ?? 0);
        }

        $attendanceRate = ($attended + $noShows) > 0
            ? round(($attended / ($attended + $noShows)) * 100, 1)
            : ($booked > 0 ? 100.0 : 0.0);

        return [
            'booked' => $booked,
            'attended' => $attended,
            'no_shows' => $noShows,
            'attendance_rate' => $attendanceRate,
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    public function leadToCash(Company $company): array
    {
        $stages = $this->stageCounts($company, 'outcome_lead_to_cash_journey_id');

        $paidRevenue = 0.0;
        $socialOrders = 0;
        $socialRevenue = 0.0;

        if (class_exists(Invoice::class)) {
            $paidRevenue = (float) Invoice::where('company_id', $company->id)
                ->where('status', 'paid')
                ->where('paid_at', '>=', now()->subDays(30))
                ->sum('amount');

            $socialQuery = Invoice::where('company_id', $company->id)
                ->where('status', 'paid')
                ->whereNotNull('social_post_id')
                ->where('paid_at', '>=', now()->subDays(30));

            $socialOrders = (int) (clone $socialQuery)->count();
            $socialRevenue = (float) (clone $socialQuery)->sum('amount');
        }

        return [
            'leads' => (int) ($stages['New Lead'] ?? 0),
            'qualified' => (int) ($stages['Qualified'] ?? 0),
            'proposals' => (int) (($stages['Proposal'] ?? 0) + ($stages['Awaiting Payment'] ?? 0)),
            'paid' => (int) ($stages['Paid'] ?? 0),
            'lost' => (int) ($stages['Lost'] ?? 0),
            'pipeline_paid_revenue' => $paidRevenue,
            'pipeline_paid_revenue_formatted' => $company->currency.' '.number_format($paidRevenue, 0),
            'social_orders' => $socialOrders,
            'social_revenue' => $socialRevenue,
            'social_revenue_formatted' => $company->currency.' '.number_format($socialRevenue, 0),
        ];
    }

    /**
     * Dashboard widget cards for revenue home screen.
     *
     * @return array<string, array<string, mixed>>
     */
    public function widgets(Company $company): array
    {
        $cart = $this->cartRecovery($company);
        $booking = $this->bookingConvert($company);
        $lead = $this->leadToCash($company);

        return [
            'outcome_cart_recovery' => [
                'title' => __('Cart recovery'),
                'icon' => 'ni-cart',
                'icon_color' => 'bg-gradient-success',
                'main_value' => $cart['recovered'],
                'sub_value' => $cart['abandoned'],
                'sub_value_color' => 'text-success',
                'sub_title' => __('open abandoned carts'),
                'href' => route('outcomes.index'),
            ],
            'outcome_booking_convert' => [
                'title' => __('Booking attendance'),
                'icon' => 'ni-calendar-grid-58',
                'icon_color' => 'bg-gradient-info',
                'main_value' => $booking['attendance_rate'].'%',
                'sub_value' => $booking['no_shows'],
                'sub_value_color' => 'text-info',
                'sub_title' => __('no-shows (journey)'),
                'href' => route('outcomes.index'),
            ],
            'outcome_lead_to_cash' => [
                'title' => __('Lead-to-cash paid'),
                'icon' => 'ni-money-coins',
                'icon_color' => 'bg-gradient-primary',
                'main_value' => $lead['paid'],
                'sub_value' => $lead['pipeline_paid_revenue_formatted'],
                'sub_value_color' => 'text-primary',
                'sub_title' => __('paid (30d)'),
                'href' => route('outcomes.index'),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function stageCounts(Company $company, string $configKey): array
    {
        if (! class_exists(Journey::class)) {
            return [];
        }

        $journeyId = (int) $company->getConfig($configKey, 0);
        if (! $journeyId) {
            return [];
        }

        $stages = JourneyStage::withoutGlobalScopes()
            ->where('journey_id', $journeyId)
            ->orderBy('order')
            ->get(['id', 'name']);

        if ($stages->isEmpty()) {
            return [];
        }

        $counts = DB::table('journey_stage_contacts')
            ->whereIn('stage_id', $stages->pluck('id'))
            ->select('stage_id', DB::raw('count(*) as aggregate'))
            ->groupBy('stage_id')
            ->pluck('aggregate', 'stage_id');

        $result = [];
        foreach ($stages as $stage) {
            $result[$stage->name] = (int) ($counts[$stage->id] ?? 0);
        }

        return $result;
    }
}
