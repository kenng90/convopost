<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use Illuminate\Console\Command;

class MigrateStaffToMemberships extends Command
{
    protected $signature = 'org:migrate-staff-memberships {--dry-run : Preview changes without writing}';

    protected $description = 'Create organization memberships for existing staff agents';

    public function handle(CompanyMembershipService $membershipService): int
    {
        $membershipService->seedSystemTemplates();

        $staffUsers = User::role('staff')->get();
        $created = 0;
        $skipped = 0;

        foreach ($staffUsers as $user) {
            if ($user->company_id === null) {
                $this->warn("Skipping staff user {$user->email} — no company_id");
                $skipped++;

                continue;
            }

            $company = Company::find($user->company_id);

            if ($company === null) {
                $this->warn("Skipping staff user {$user->email} — company not found");
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Would migrate agent membership: {$user->email} → company {$company->id}");
                $created++;

                continue;
            }

            $membershipService->ensureAgentMembership($user, $company, $company->user);
            $this->line("Migrated agent membership: {$user->email} → company {$company->id}");
            $created++;
        }

        $this->info("Done. Created/verified {$created} memberships, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
