<?php

namespace App\Services\Workspace;

use App\Models\Company;
use App\Models\ConversationWorkspace;
use App\Services\Trust\AuditLogger;
use Modules\Wpbox\Models\Contact;

class ConversationSlaService
{
    public function start(Company $company, Contact $contact): ConversationWorkspace
    {
        $minutes = max(5, (int) $company->getConfig('inbox_sla_minutes', '30'));
        $workspace = $this->workspace($company, $contact);

        if ($workspace->sla_started_at && ! $contact->resolved_chat) {
            return $workspace;
        }

        $workspace->fill([
            'sla_started_at' => now(),
            'sla_due_at' => now()->addMinutes($minutes),
            'sla_breached_at' => null,
            'sla_minutes' => $minutes,
        ]);
        $workspace->save();

        return $workspace;
    }

    public function clearOnResolve(Company $company, Contact $contact): void
    {
        $workspace = $this->workspace($company, $contact);
        $workspace->sla_due_at = null;
        $workspace->sla_breached_at = null;
        $workspace->save();
    }

    public function markBreaches(Company $company): int
    {
        $rows = ConversationWorkspace::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('sla_breached_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->get();

        foreach ($rows as $row) {
            $row->sla_breached_at = now();
            $row->save();
            app(AuditLogger::class)->log($company, 'sla.breached', Contact::class, $row->contact_id);
        }

        return $rows->count();
    }

    public function workspace(Company $company, Contact $contact): ConversationWorkspace
    {
        return ConversationWorkspace::withoutGlobalScopes()->firstOrCreate(
            ['contact_id' => $contact->id],
            ['company_id' => $company->id]
        );
    }
}
