<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Console\Command;

class CatalogCanonicalizeItems extends Command
{
    protected $signature = 'catalog:canonicalize-items {--company=}';

    protected $description = 'Migrate catalog JSON items into the relational catalog_items table';

    public function handle(CatalogItemRepository $catalogItemRepository): int
    {
        $companyId = $this->option('company');

        if ($companyId) {
            $count = $catalogItemRepository->canonicalizeCompany((int) $companyId);
            $this->info("Canonicalized {$count} catalog(s) for company {$companyId}.");

            return self::SUCCESS;
        }

        $total = 0;
        Company::query()->orderBy('id')->chunkById(50, function ($companies) use ($catalogItemRepository, &$total) {
            foreach ($companies as $company) {
                $total += $catalogItemRepository->canonicalizeCompany($company->id);
            }
        });

        $this->info("Canonicalized {$total} catalog(s).");

        return self::SUCCESS;
    }
}
