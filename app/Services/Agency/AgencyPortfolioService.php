<?php

namespace App\Services\Agency;

use App\Models\Company;
use App\Models\User;
use App\Services\Outcomes\OutcomeMetricsService;
use App\Services\Outcomes\PlaybookInstaller;
use App\Services\Trust\AuditLogger;

class AgencyPortfolioService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function companies(User $owner): array
    {
        return $owner->accessibleCompanies()->map(function (Company $company) {
            $metrics = app(OutcomeMetricsService::class)->forCompany($company);

            return [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain ?? null,
                'cart_recovered' => $metrics['cart_recovery']['recovered'] ?? 0,
                'attendance_rate' => $metrics['booking_convert']['attendance_rate'] ?? 0,
                'paid' => $metrics['lead_to_cash']['paid'] ?? 0,
            ];
        })->values()->all();
    }

    /**
     * Clone an installed playbook from one tenant to another.
     *
     * @return array{success: bool, message: string}
     */
    public function clonePlaybook(User $owner, Company $from, Company $to, string $playbook): array
    {
        if ((int) $from->user_id !== (int) $owner->id || (int) $to->user_id !== (int) $owner->id) {
            return ['success' => false, 'message' => __('You can only copy playbooks between your own workspaces.')];
        }

        $result = app(PlaybookInstaller::class)->install($to, $playbook, true, true);

        app(AuditLogger::class)->log($to, 'agency.clone_playbook', Company::class, $from->id, [
            'playbook' => $playbook,
        ]);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? __('Playbook copied.'),
        ];
    }

    /**
     * @return array{companies: int, recovered: int, paid: int}
     */
    public function rollup(User $owner): array
    {
        $rows = $this->companies($owner);

        return [
            'companies' => count($rows),
            'recovered' => (int) collect($rows)->sum('cart_recovered'),
            'paid' => (int) collect($rows)->sum('paid'),
        ];
    }
}
