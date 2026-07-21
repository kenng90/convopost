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
                'category' => \App\Support\WhatsappFlowCategory::normalize($template['category'] ?? 'OTHER'),
                'industry' => $template['industry'] ?? null,
                'screen_count' => count($template['screens'] ?? []),
            ];
        })->values()->all();
    }

    /**
     * Reuse an existing form for this company + bundle, or create a new draft.
     * Used when installing Flowmaker templates that ship with a form_bundle.
     */
    public function findOrCreateFromTemplate(string $key, int $companyId, ?string $customName = null): WhatsappFlow
    {
        $existing = WhatsappFlow::query()
            ->where('company_id', $companyId)
            ->where('form_bundle_key', $key)
            ->where('status', '!=', 'archived')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createFromTemplate($key, $companyId, $customName);
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

        $baseName = $customName ?? ($template['name'] ?? 'New Form');

        return WhatsappFlow::create([
            'company_id' => $companyId,
            'name' => $this->uniqueNameForCompany($companyId, $baseName),
            'description' => $template['description'] ?? '',
            'category' => \App\Support\WhatsappFlowCategory::normalize($template['category'] ?? 'OTHER'),
            'flow_json' => ['screens' => $template['screens'] ?? []],
            'status' => 'draft',
            'version' => 1,
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

    /**
     * Avoid company_id + name + version unique collisions (including soft-deleted rows).
     */
    public function uniqueNameForCompany(int $companyId, string $baseName, int $version = 1): string
    {
        $name = trim($baseName) !== '' ? trim($baseName) : 'New Form';
        $candidate = $name;
        $suffix = 2;

        while (
            WhatsappFlow::withTrashed()
                ->where('company_id', $companyId)
                ->where('name', $candidate)
                ->where('version', $version)
                ->exists()
        ) {
            $candidate = "{$name} ({$suffix})";
            $suffix++;
        }

        return $candidate;
    }
}
