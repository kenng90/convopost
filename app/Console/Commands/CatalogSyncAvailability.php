<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Catalog\CatalogAvailabilitySyncService;
use Illuminate\Console\Command;

class CatalogSyncAvailability extends Command
{
    protected $signature = 'catalog:sync-availability {--company=}';

    protected $description = 'Sync listing/service catalog availability from Reminders calendars';

    public function handle(CatalogAvailabilitySyncService $syncService): int
    {
        $companyId = $this->option('company');

        if ($companyId) {
            $company = Company::find($companyId);
            if (! $company) {
                $this->error("Company {$companyId} not found.");

                return self::FAILURE;
            }

            $updated = $syncService->syncCompany($company);
            $this->info("Updated availability for {$updated} item(s) in company {$companyId}.");

            return self::SUCCESS;
        }

        $total = 0;
        Company::query()->orderBy('id')->chunkById(50, function ($companies) use ($syncService, &$total) {
            foreach ($companies as $company) {
                try {
                    $total += $syncService->syncCompany($company);
                } catch (\Throwable $e) {
                    $this->error("Company {$company->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Updated availability for {$total} item(s).");

        return self::SUCCESS;
    }
}
