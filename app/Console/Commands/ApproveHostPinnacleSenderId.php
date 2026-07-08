<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\ConvoConnectBrand;
use Illuminate\Console\Command;

class ApproveHostPinnacleSenderId extends Command
{
    protected $signature = 'convoconnect:approve-sender {company : Company ID}';

    protected $description = 'Mark a company ConvoConnect Sender ID as approved (after CA/operator approval)';

    public function handle(): int
    {
        $company = Company::query()->find($this->argument('company'));

        if ($company === null) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $senderId = trim((string) $company->getConfig('HOSTPINNACLE_SENDER_ID', ''));
        if ($senderId === '') {
            $this->error('Company has no '.ConvoConnectBrand::name().' Sender ID. Run convoconnect:provision-companies first.');

            return self::FAILURE;
        }

        $company->setConfig('HOSTPINNACLE_SENDER_STATUS', 'approved');
        $this->info("Sender ID [{$senderId}] marked approved for company #{$company->id} ({$company->name}).");

        return self::SUCCESS;
    }
}
