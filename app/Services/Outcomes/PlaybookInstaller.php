<?php

namespace App\Services\Outcomes;

use App\Models\Company;
use App\Services\Flowmaker\FlowTemplateService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Flow;
use Modules\Journies\Models\Journey;
use Modules\Journies\Services\JourneyTemplateService;

class PlaybookInstaller
{
    public function __construct(
        private readonly JourneyTemplateService $journeys,
        private readonly FlowTemplateService $flows,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('outcome-playbooks.playbooks', []);
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     journey?: Journey|null,
     *     flow?: Flow|null,
     *     playbook?: array<string, mixed>,
     *     already_installed?: bool
     * }
     */
    public function install(Company $company, string $key, bool $installFlow = true, bool $force = false): array
    {
        $playbook = $this->get($key);

        if (! $playbook) {
            return ['success' => false, 'message' => __('Unknown playbook.')];
        }

        $previousCompany = session('company_id');
        session(['company_id' => $company->id]);

        try {
            if (! $force && $company->getConfig($playbook['config_installed_key'], 'no') === 'yes') {
                $existingJourneyId = (int) $company->getConfig($playbook['config_journey_key'], 0);
                $journey = $existingJourneyId
                    ? Journey::withoutGlobalScopes()->where('company_id', $company->id)->find($existingJourneyId)
                    : null;

                return [
                    'success' => true,
                    'already_installed' => true,
                    'message' => __(':name is already installed.', ['name' => $playbook['name']]),
                    'journey' => $journey,
                    'flow' => $this->resolveFlow($company, $playbook),
                    'playbook' => $playbook,
                ];
            }

            $journey = $this->journeys->createFromTemplate($playbook['journey_template']);

            if (! $journey) {
                return ['success' => false, 'message' => __('Could not create journey from template.')];
            }

            $flow = null;
            if ($installFlow && ! empty($playbook['flow_template'])) {
                try {
                    $flow = $this->flows->install($playbook['flow_template'], $playbook['name'].' Flow', [
                        'journey_id' => $journey->id,
                        'stage_id' => $journey->stages->first()?->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Outcome playbook flow install failed', [
                        'playbook' => $key,
                        'company_id' => $company->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $company->setMultipleConfig([
                $playbook['config_journey_key'] => (string) $journey->id,
                $playbook['config_installed_key'] => 'yes',
                $playbook['config_flow_key'] => $flow ? (string) $flow->id : '',
            ]);

            if ($key === 'lead_to_cash') {
                $company->setConfig('JOURNEYS_DEFAULT_JOURNEY_ID', (string) $journey->id);
            }

            return [
                'success' => true,
                'message' => __(':name installed — journey and automation ready.', ['name' => $playbook['name']]),
                'journey' => $journey->load('stages'),
                'flow' => $flow,
                'playbook' => $playbook,
            ];
        } finally {
            if ($previousCompany !== null) {
                session(['company_id' => $previousCompany]);
            }
        }
    }

    /**
     * @return array{success: bool, message: string, results: array<string, array<string, mixed>>}
     */
    public function installSuite(Company $company, bool $installFlows = true, bool $force = false): array
    {
        $results = [];
        $failed = [];

        foreach (array_keys($this->all()) as $key) {
            $result = $this->install($company, $key, $installFlows, $force);
            $results[$key] = $result;
            if (! ($result['success'] ?? false)) {
                $failed[] = $key;
            }
        }

        if ($failed === []) {
            $company->setConfig('outcome_commerce_ops_suite_installed', 'yes');
        }

        return [
            'success' => $failed === [],
            'message' => $failed === []
                ? __('Commerce Ops Suite installed (all three playbooks).')
                : __('Some playbooks failed to install.'),
            'results' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusForCompany(Company $company): array
    {
        $items = [];

        foreach ($this->all() as $key => $playbook) {
            $installed = $company->getConfig($playbook['config_installed_key'], 'no') === 'yes';
            $journeyId = (int) $company->getConfig($playbook['config_journey_key'], 0);
            $flowId = (int) $company->getConfig($playbook['config_flow_key'], 0);

            $items[$key] = [
                'key' => $key,
                'name' => $playbook['name'],
                'tagline' => $playbook['tagline'],
                'description' => $playbook['description'],
                'icon' => $playbook['icon'],
                'color' => $playbook['color'],
                'checklist' => $playbook['checklist'],
                'estimated_credits_per_100' => $playbook['estimated_credits_per_100'],
                'installed' => $installed,
                'journey_id' => $journeyId ?: null,
                'flow_id' => $flowId ?: null,
                'credit_estimate_total' => $this->estimateCredits($playbook),
            ];
        }

        return [
            'playbooks' => $items,
            'suite_installed' => $company->getConfig('outcome_commerce_ops_suite_installed', 'no') === 'yes'
                || collect($items)->every(fn ($item) => $item['installed']),
        ];
    }

    /**
     * @param  array<string, mixed>  $playbook
     */
    public function estimateCredits(array $playbook): int
    {
        $costs = config('outcome-playbooks.credit_action_estimates', []);
        $estimate = $playbook['estimated_credits_per_100'] ?? [];

        return (int) (
            (($estimate['marketing'] ?? 0) / 100) * ($costs['send_campaign_marketing'] ?? 3) * 100
            + (($estimate['utility'] ?? 0) / 100) * ($costs['send_template_utility'] ?? 1) * 100
            + (($estimate['bot'] ?? 0) / 100) * ($costs['send_bot_auto_reply'] ?? 1) * 100
        );
    }

    /**
     * @param  array<string, mixed>  $playbook
     */
    private function resolveFlow(Company $company, array $playbook): ?Flow
    {
        $flowId = (int) $company->getConfig($playbook['config_flow_key'], 0);

        if (! $flowId || ! class_exists(Flow::class)) {
            return null;
        }

        return Flow::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->find($flowId);
    }
}
