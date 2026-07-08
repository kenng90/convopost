<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\HostPinnacle\HostPinnacleProvisioner;

class CompanyObserver
{
    public function __construct(
        private readonly HostPinnacleProvisioner $provisioner,
    ) {
    }

    public function created(Company $company): void
    {
        if (! config('hostpinnacle.enabled', false)) {
            return;
        }

        if (! config('hostpinnacle.auto_provision_on_company_create', false)) {
            return;
        }

        $this->provisioner->provision($company);
    }
}
