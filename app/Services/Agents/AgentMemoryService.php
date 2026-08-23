<?php

namespace App\Services\Agents;

use App\Models\AgentMemory;
use App\Models\Company;
use Modules\Wpbox\Models\Contact;

class AgentMemoryService
{
    /**
     * @param  array<int, string>  $toolsUsed
     * @param  array<string, mixed>  $metadata
     */
    public function remember(
        Company $company,
        Contact $contact,
        string $userMessage,
        string $reply,
        array $toolsUsed = [],
        array $metadata = [],
        string $channel = 'chat',
    ): AgentMemory {
        return AgentMemory::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => $channel,
            'user_message' => $userMessage,
            'reply' => $reply,
            'tools_used' => $toolsUsed,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function recentTurns(Company $company, Contact $contact, int $limit = 8): array
    {
        return AgentMemory::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->flatMap(function (AgentMemory $memory) {
                $rows = [];
                if (filled($memory->user_message)) {
                    $rows[] = ['role' => 'user', 'content' => (string) $memory->user_message];
                }
                if (filled($memory->reply)) {
                    $rows[] = ['role' => 'assistant', 'content' => (string) $memory->reply];
                }

                return $rows;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingCharge(Company $company, Contact $contact): ?array
    {
        $memory = AgentMemory::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->get()
            ->first(fn (AgentMemory $row) => ! empty(($row->metadata['pending_charge'] ?? null)));

        return is_array($memory?->metadata['pending_charge'] ?? null)
            ? $memory->metadata['pending_charge']
            : null;
    }
}
