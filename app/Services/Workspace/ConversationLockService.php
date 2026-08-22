<?php

namespace App\Services\Workspace;

use App\Models\Company;
use App\Models\ConversationWorkspace;
use App\Models\User;
use Modules\Wpbox\Models\Contact;

class ConversationLockService
{
    public function lock(Company $company, Contact $contact, User $user): ConversationWorkspace
    {
        $workspace = app(ConversationSlaService::class)->workspace($company, $contact);
        $workspace->locked_by = $user->id;
        $workspace->locked_at = now();
        $workspace->save();

        return $workspace;
    }

    public function unlock(Company $company, Contact $contact): void
    {
        $workspace = app(ConversationSlaService::class)->workspace($company, $contact);
        $workspace->locked_by = null;
        $workspace->locked_at = null;
        $workspace->save();
    }

    public function isLockedByOther(Company $company, Contact $contact, User $user): bool
    {
        $workspace = ConversationWorkspace::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->first();

        if (! $workspace?->locked_by) {
            return false;
        }

        if ($workspace->locked_at && $workspace->locked_at->lt(now()->subMinutes(10))) {
            return false;
        }

        return (int) $workspace->locked_by !== (int) $user->id;
    }
}
