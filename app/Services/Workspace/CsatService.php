<?php

namespace App\Services\Workspace;

use App\Models\Company;
use App\Models\ConversationWorkspace;
use App\Services\Trust\AuditLogger;
use Modules\Wpbox\Models\Contact;

class CsatService
{
    public function request(Company $company, Contact $contact): ConversationWorkspace
    {
        $workspace = app(ConversationSlaService::class)->workspace($company, $contact);
        $workspace->csat_requested_at = now();
        $workspace->save();

        $prompt = (string) $company->getConfig(
            'csat_prompt',
            'How would you rate this conversation from 1 (poor) to 5 (excellent)?'
        );

        $contact->sendMessage($prompt, false, false, 'TEXT', null, null, 'send_bot_auto_reply', true);

        return $workspace;
    }

    public function submit(Company $company, Contact $contact, int $score, ?string $comment = null): ConversationWorkspace
    {
        $workspace = app(ConversationSlaService::class)->workspace($company, $contact);
        $workspace->csat_score = max(1, min(5, $score));
        $workspace->csat_comment = $comment;
        $workspace->csat_submitted_at = now();
        $workspace->save();

        app(AuditLogger::class)->log($company, 'csat.submitted', Contact::class, $contact->id, [
            'score' => $workspace->csat_score,
        ]);

        return $workspace;
    }

    /**
     * @return array{requested: int, submitted: int, average: float|null}
     */
    public function summary(Company $company): array
    {
        $rows = ConversationWorkspace::withoutGlobalScopes()
            ->where('company_id', $company->id);

        $submitted = (clone $rows)->whereNotNull('csat_score')->count();
        $average = $submitted
            ? round((float) (clone $rows)->whereNotNull('csat_score')->avg('csat_score'), 2)
            : null;

        return [
            'requested' => (clone $rows)->whereNotNull('csat_requested_at')->count(),
            'submitted' => $submitted,
            'average' => $average,
        ];
    }
}
