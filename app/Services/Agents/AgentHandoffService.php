<?php

namespace App\Services\Agents;

use App\Models\Company;
use App\Models\User;
use App\Services\Trust\AuditLogger;
use App\Services\Workspace\InboxRoutingService;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Models\Contact;

class AgentHandoffService
{
    /**
     * @return array{ok: bool, assigned_to: int|null, bot_disabled: bool, reason: string}
     */
    public function handoff(Company $company, Contact $contact, string $reason = 'Talk to a human', ?int $agentId = null): array
    {
        $agentId ??= app(InboxRoutingService::class)->nextAgentId($company);

        if ($agentId) {
            $agent = User::query()
                ->where('id', $agentId)
                ->where('company_id', $company->id)
                ->first();
            if ($agent) {
                $contact->user_id = $agent->id;
            }
        }

        $contact->enabled_ai_bot = false;
        $contact->voice_handoff_pending = true;
        $contact->has_chat = true;
        $contact->is_last_message_by_contact = true;
        $contact->last_reply_at = now();
        $contact->last_message = $contact->trimString(__('Needs a human agent'), 40);
        $contact->save();

        $note = __('AI handoff: :reason', ['reason' => $reason]);
        if (method_exists($contact, 'addNote')) {
            $contact->addNote($note);
        }

        app(AuditLogger::class)->log($company, 'agent.handoff', Contact::class, $contact->id, [
            'reason' => $reason,
            'assigned_to' => $contact->user_id,
        ]);

        try {
            event(new Chatlistchange($contact->id, $contact->company_id));
        } catch (\Throwable) {
        }

        return [
            'ok' => true,
            'assigned_to' => $contact->user_id ? (int) $contact->user_id : null,
            'bot_disabled' => true,
            'reason' => $reason,
        ];
    }

    public function disableBotOnAssign(Contact $contact): void
    {
        if (! $contact->user_id) {
            return;
        }

        $contact->enabled_ai_bot = false;
        $contact->save();
    }
}
