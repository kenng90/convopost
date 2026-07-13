<?php

namespace App\Services\Flowmaker;

use App\Services\WhatsappFormTemplateService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Flow;

class FlowTemplateService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('flow-templates', []);
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  array{
     *     catalog_id?: int|string|null,
     *     payment_provider?: string|null,
     *     group_id?: int|string|null,
     *     journey_id?: int|string|null,
     *     stage_id?: int|string|null,
     *     keywords?: array<int, string>|null
     * }  $bindings
     */
    public function install(string $key, ?string $customName = null, array $bindings = []): ?Flow
    {
        if (str_starts_with($key, 'whatsapp_form_')) {
            $recipe = substr($key, strlen('whatsapp_form_'));
            $formId = $bindings['whatsapp_flow_id'] ?? $bindings['form_id'] ?? null;
            if (! $formId) {
                return null;
            }

            $form = \App\Models\WhatsappFlow::query()->find($formId);
            if (! $form) {
                return null;
            }

            return app(WhatsappFormAutomationFactory::class)->createFromForm(
                $form,
                $recipe,
                (int) ($form->company_id)
            );
        }

        $template = $this->get($key);
        if (! $template) {
            return null;
        }

        $companyId = session('company_id') ?? auth()->user()?->currentCompany()?->id;

        $flowData = $template['flow_data'];
        if (! empty($template['form_bundle']) && $companyId) {
            $whatsappForm = app(WhatsappFormTemplateService::class)->createFromTemplate(
                $template['form_bundle'],
                (int) $companyId
            );

            $company = \App\Models\Company::find($companyId);
            if ($company && app(\App\Services\WhatsappFlowReadinessService::class)->companyCanAutoPublish($company)) {
                try {
                    $publish = app(\App\Services\WhatsappMetaFlowService::class)->publishFlow($whatsappForm);
                    if (! ($publish['success'] ?? false)) {
                        Log::warning('WhatsApp form auto-publish failed on template install', [
                            'form_id' => $whatsappForm->id,
                            'message' => $publish['message'] ?? null,
                        ]);
                    } else {
                        $whatsappForm->refresh();
                    }
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp form auto-publish exception on template install', [
                        'form_id' => $whatsappForm->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $flowData = $this->linkBundledWhatsappForms($flowData, $whatsappForm->id);
        }

        $flowData = $this->applyBindings($flowData, $bindings);

        $flow = Flow::create([
            'name' => $customName ?: $template['name'],
            'company_id' => $companyId,
            'priority' => 10,
            'exclusive_on_match' => $this->shouldInstallExclusive($template),
            'is_active' => true,
        ]);

        $encoded = json_encode($flowData);
        $flow->flow_data = $encoded;
        $flow->draft_flow_data = $encoded;
        $flow->has_unpublished_changes = false;
        $flow->source_template = $key;
        $flow->save();

        $company = $flow->company ?? auth()->user()?->currentCompany();
        if ($company) {
            $company->setConfig('activation_flow_installed', 'yes');
        }

        return $flow;
    }

    /**
     * Keyword-owned verticals should not collide with parallel flows on the same trigger words.
     *
     * @param  array<string, mixed>  $template
     */
    private function shouldInstallExclusive(array $template): bool
    {
        if (array_key_exists('exclusive_on_match', $template)) {
            return (bool) $template['exclusive_on_match'];
        }

        return ($template['category'] ?? '') === 'commerce'
            || ! empty($template['requires_setup_wizard']);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupedByCategory(): array
    {
        $grouped = [];
        foreach ($this->all() as $key => $template) {
            $category = $template['category'] ?? 'general';
            $grouped[$category][] = array_merge($template, ['key' => $key]);
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $flowData
     * @param  array<string, mixed>  $bindings
     * @return array<string, mixed>
     */
    public function applyBindings(array $flowData, array $bindings): array
    {
        $nodes = $flowData['nodes'] ?? [];
        $edges = $flowData['edges'] ?? [];
        $catalogId = $bindings['catalog_id'] ?? null;
        $provider = $bindings['payment_provider'] ?? null;
        $groupId = $bindings['group_id'] ?? null;
        $journeyId = $bindings['journey_id'] ?? null;
        $stageId = $bindings['stage_id'] ?? null;
        $keywords = $bindings['keywords'] ?? null;
        $paymentIdMap = [];

        foreach ($nodes as $index => $node) {
            $type = $node['type'] ?? '';
            $settings = $node['data']['settings'] ?? [];

            if (in_array($type, ['whatsapp_catalog', 'catalog_search', 'listing_inquiry'], true) && $catalogId) {
                $settings['catalogId'] = (string) $catalogId;
            }

            if ($type === 'request_payment' && $provider) {
                $payment = $settings['payment'] ?? [];
                $payment['provider'] = $provider;
                $settings['payment'] = $payment;
            }

            // Legacy template safety: map old M-Pesa node settings onto request_payment shape if still present.
            if ($type === 'mpesa_stk_push') {
                $oldId = (string) ($node['id'] ?? 'mpesa_stk_push-1');
                $newId = str_replace('mpesa_stk_push', 'request_payment', $oldId);
                $mpesa = $settings['mpesa'] ?? $settings;
                $paymentIdMap[$oldId] = $newId;
                $nodes[$index]['id'] = $newId;
                $nodes[$index]['type'] = 'request_payment';
                $nodes[$index]['data']['type'] = 'request_payment';
                $nodes[$index]['data']['label'] = $node['data']['label'] ?? 'Request payment';
                $settings = [
                    'payment' => [
                        'amount' => (string) ($mpesa['amount'] ?? '0'),
                        'accountReference' => (string) ($mpesa['accountReference'] ?? 'ORDER'),
                        'description' => (string) ($mpesa['transactionDesc'] ?? $mpesa['description'] ?? 'Payment'),
                        'provider' => $provider ?: 'auto',
                        'email' => (string) ($mpesa['email'] ?? ''),
                        'responseVar' => (string) ($mpesa['responseVar'] ?? 'payment_result'),
                    ],
                ];
                $type = 'request_payment';
            }

            if ($type === 'assign_group' && $groupId) {
                $settings['groupId'] = (string) $groupId;
            }

            if ($type === 'assign_journey_stage') {
                if ($journeyId) {
                    $settings['journeyId'] = (string) $journeyId;
                }
                if ($stageId) {
                    $settings['stageId'] = (string) $stageId;
                }
            }

            if ($type === 'keyword_trigger' && is_array($keywords) && $keywords !== []) {
                $keywordRows = [];
                foreach (array_values($keywords) as $i => $value) {
                    if (trim((string) $value) === '') {
                        continue;
                    }
                    $keywordRows[] = [
                        'id' => 'kw'.($i + 1),
                        'value' => trim((string) $value),
                        'matchType' => 'contains',
                    ];
                }
                if ($keywordRows !== []) {
                    $nodes[$index]['data']['keywords'] = $keywordRows;
                }
            }

            if ($settings !== ($node['data']['settings'] ?? [])) {
                $nodes[$index]['data']['settings'] = $settings;
            }
        }

        if ($paymentIdMap !== []) {
            foreach ($edges as $edgeIndex => $edge) {
                if (isset($paymentIdMap[$edge['source'] ?? ''])) {
                    $edges[$edgeIndex]['source'] = $paymentIdMap[$edge['source']];
                }
                if (isset($paymentIdMap[$edge['target'] ?? ''])) {
                    $edges[$edgeIndex]['target'] = $paymentIdMap[$edge['target']];
                }
                $handle = (string) ($edge['sourceHandle'] ?? '');
                if ($handle === 'mpesa-success') {
                    $edges[$edgeIndex]['sourceHandle'] = 'success';
                } elseif ($handle === 'mpesa-failed') {
                    $edges[$edgeIndex]['sourceHandle'] = 'failed';
                }
            }
        }

        $flowData['nodes'] = $nodes;
        $flowData['edges'] = $edges;

        return $flowData;
    }

    /**
     * @param  array<string, mixed>  $flowData
     * @return array<string, mixed>
     */
    private function linkBundledWhatsappForms(array $flowData, int $whatsappFlowId): array
    {
        $nodes = $flowData['nodes'] ?? [];

        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? '') !== 'whatsapp_flow') {
                continue;
            }

            $settings = $node['data']['settings'] ?? [];
            if (! empty($settings['whatsappFlowId'])) {
                continue;
            }

            $settings['whatsappFlowId'] = $whatsappFlowId;
            $nodes[$index]['data']['settings'] = $settings;
        }

        $flowData['nodes'] = $nodes;

        return $flowData;
    }
}
