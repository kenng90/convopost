<?php

namespace App\Services\Flowmaker;

use App\Services\WhatsappFormTemplateService;
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

    public function install(string $key, ?string $customName = null): ?Flow
    {
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
            $flowData = $this->linkBundledWhatsappForms($flowData, $whatsappForm->id);
        }

        $flow = Flow::create([
            'name' => $customName ?: $template['name'],
            'company_id' => $companyId,
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
