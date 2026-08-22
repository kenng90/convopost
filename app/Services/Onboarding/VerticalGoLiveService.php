<?php

namespace App\Services\Onboarding;

use App\Models\Company;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\Outcomes\PlaybookInstaller;
use App\Services\Trust\AuditLogger;

class VerticalGoLiveService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function packs(): array
    {
        return config('vertical-golive.packs', []);
    }

    /**
     * @return array{success: bool, message: string, vertical?: string, flow_id?: int|null, playbook?: array<string, mixed>|null}
     */
    public function install(Company $company, string $vertical, bool $installPlaybook = true): array
    {
        $pack = $this->packs()[$vertical] ?? null;
        if (! $pack) {
            return ['success' => false, 'message' => __('Unknown vertical pack.')];
        }

        $previous = session('company_id');
        session(['company_id' => $company->id]);

        try {
            $flow = app(FlowTemplateService::class)->install(
                $pack['flow_template'],
                $pack['name']
            );

            $playbookResult = null;
            if ($installPlaybook && ! empty($pack['playbook'])) {
                $playbookResult = app(PlaybookInstaller::class)->install(
                    $company,
                    $pack['playbook'],
                    false,
                    false
                );
            }

            $company->setConfig('activation_flow_installed', 'yes');
            $company->setConfig('vertical_golive_pack', $vertical);

            app(AuditLogger::class)->log($company, 'vertical.golive', null, null, [
                'vertical' => $vertical,
                'flow_id' => $flow?->id,
            ]);

            return [
                'success' => (bool) $flow,
                'message' => $flow
                    ? __(':name is live. Review and publish the flow.', ['name' => $pack['name']])
                    : __('Could not install the flow template.'),
                'vertical' => $vertical,
                'flow_id' => $flow?->id,
                'playbook' => $playbookResult,
            ];
        } finally {
            session(['company_id' => $previous]);
        }
    }
}
