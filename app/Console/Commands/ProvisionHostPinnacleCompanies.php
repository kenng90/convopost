<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\HostPinnacle\HostPinnacleProvisioner;
use App\Support\ConvoConnectBrand;
use Illuminate\Console\Command;

class ProvisionHostPinnacleCompanies extends Command
{
    protected $signature = 'convoconnect:provision-companies {--company= : Provision a single company ID}';

    protected $description = 'Provision ConvoConnect sub-accounts and sender IDs for companies';

    public function handle(HostPinnacleProvisioner $provisioner): int
    {
        if (! config('hostpinnacle.enabled', false)) {
            $this->error(ConvoConnectBrand::name().' is disabled. Set HOSTPINNACLE_ENABLED=true first.');

            return self::FAILURE;
        }

        $companyId = $this->option('company');
        $query = Company::query()->orderBy('id');
        if ($companyId) {
            $query->whereKey($companyId);
        }

        $provisioned = 0;
        foreach ($query->cursor() as $company) {
            if ($provisioner->provision($company)) {
                $provisioned++;
                $this->line("Provisioned company #{$company->id} ({$company->name})");
            } else {
                $this->warn("Skipped company #{$company->id} ({$company->name})");
            }
        }

        $this->info("Finished. Provisioned {$provisioned} companies.");

        return self::SUCCESS;
    }
}
