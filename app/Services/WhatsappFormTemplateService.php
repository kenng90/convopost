<?php

namespace App\Services;

use App\Models\WhatsappFlow;

class WhatsappFormTemplateService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('whatsapp-form-templates', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForGallery(): array
    {
        return collect($this->all())->map(function (array $template, string $key) {
            return [
                'key' => $key,
                'name' => $template['name'] ?? $key,
                'description' => $template['description'] ?? '',
                'category' => $template['category'] ?? 'OTHER',
                'industry' => $template['industry'] ?? null,
                'screen_count' => count($template['screens'] ?? []),
            ];
        })->values()->all();
    }

    /**
     * Create a draft form from a template bundle.
     */
    public function createFromTemplate(string $key, int $companyId, ?string $customName = null): WhatsappFlow
    {
        $template = $this->get($key);
        if (! $template) {
            throw new \InvalidArgumentException("Form template [{$key}] not found.");
        }

        return WhatsappFlow::create([
            'company_id' => $companyId,
            'name' => $customName ?? ($template['name'] ?? 'New Form'),
            'description' => $template['description'] ?? '',
            'category' => $template['category'] ?? 'OTHER',
            'flow_json' => ['screens' => $template['screens'] ?? []],
            'status' => 'draft',
            'form_bundle_key' => $key,
        ]);
    }

    /**
     * Resolve the form bundle key for an industry automation template.
     */
    public function bundleKeyForAutomation(string $automationKey): ?string
    {
        foreach ($this->all() as $key => $template) {
            if (($template['automation_bundle'] ?? null) === $automationKey) {
                return $key;
            }
        }

        return null;
    }
}
