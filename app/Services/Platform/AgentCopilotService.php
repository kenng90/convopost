<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Services\Agents\ActionAgentService;
use Illuminate\Support\Str;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Template;

class AgentCopilotService
{
    /**
     * @return array{suggestions: array<int, array{text: string, source: string, confidence: float}>, tone_variants: array<string, string>}
     */
    public function suggest(Company $company, Contact $contact, ?string $draft = null): array
    {
        $query = $draft ?: (string) ($contact->last_message ?? 'help');
        $agent = app(ActionAgentService::class)->reason($company, $contact, $query, 'copilot');
        $text = $agent['reply'] ?: __('Hello! How can I help you today?');

        $suggestions = [[
            'text' => $text,
            'source' => $agent['source'] === 'llm' ? __('Action agent') : __('Workspace copilot'),
            'confidence' => $agent['source'] === 'llm' ? 0.9 : 0.7,
        ]];

        return [
            'suggestions' => $suggestions,
            'tone_variants' => [
                'professional' => $text,
                'friendly' => __('Hi :name — ', ['name' => $contact->name ?: __('there')]).$text,
                'concise' => Str::limit($text, 140),
            ],
            'tools_used' => $agent['tools_used'],
        ];
    }

    public function approvedTemplates(Company $company): array
    {
        return Template::where('status', 'APPROVED')
            ->select('id', 'name', 'language')
            ->limit(10)
            ->get()
            ->map(fn (Template $t) => ['id' => $t->id, 'name' => $t->name, 'language' => $t->language])
            ->all();
    }
}
