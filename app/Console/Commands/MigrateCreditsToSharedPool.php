<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Credit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCreditsToSharedPool extends Command
{
    protected $signature = 'credits:migrate-to-shared-pool {--dry-run : Preview changes without writing}';

    protected $description = 'Assign existing company-scoped credits to their owner user and merge duplicate plan grants';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $credits = Credit::query()
            ->whereNotNull('company_id')
            ->whereNull('user_id')
            ->get();

        $this->info(sprintf('Found %d credit rows to assign to owners.', $credits->count()));

        foreach ($credits as $credit) {
            $company = Company::query()->find($credit->company_id);

            if ($company?->user_id === null) {
                $this->warn("Skipping credit {$credit->id}: company {$credit->company_id} has no owner.");

                continue;
            }

            if ($dryRun) {
                $this->line("Would assign credit {$credit->id} to user {$company->user_id}.");

                continue;
            }

            $credit->user_id = $company->user_id;
            $credit->save();
        }

        $duplicateSources = DB::table('credit')
            ->whereNotNull('user_id')
            ->where('source', 'like', 'plan:%')
            ->whereNull('deleted_at')
            ->select('user_id', 'source')
            ->groupBy('user_id', 'source')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->info(sprintf('Found %d duplicate plan credit sources to merge.', $duplicateSources->count()));

        foreach ($duplicateSources as $duplicate) {
            $rows = Credit::query()
                ->where('user_id', $duplicate->user_id)
                ->where('source', $duplicate->source)
                ->orderBy('id')
                ->get();

            $primary = $rows->shift();

            if ($primary === null) {
                continue;
            }

            foreach ($rows as $extra) {
                if ($dryRun) {
                    $this->line("Would merge credit {$extra->id} into {$primary->id} for user {$duplicate->user_id}.");

                    continue;
                }

                $primary->credit_amount += $extra->credit_amount;
                $primary->remaining_credit_amount += $extra->remaining_credit_amount;
                $primary->used_credit_amount += $extra->used_credit_amount;
                $primary->save();
                $extra->delete();
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Shared credit migration complete.');

        return self::SUCCESS;
    }
}
