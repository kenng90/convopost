<?php

namespace App\Services\Flowmaker;

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

        $flow = Flow::create([
            'name' => $customName ?: $template['name'],
            'company_id' => session('company_id') ?? auth()->user()?->currentCompany()?->id,
        ]);

        $flow->flow_data = json_encode($template['flow_data']);
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
}
