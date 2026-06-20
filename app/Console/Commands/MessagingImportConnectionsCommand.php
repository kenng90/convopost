<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Messaging\ChannelConnectionService;
use Illuminate\Console\Command;

class MessagingImportConnectionsCommand extends Command
{
    protected $signature = 'messaging:import-connections {--company= : Limit to a company ID}';

    protected $description = 'Import WhatsApp company configs into channel_connections records';

    public function handle(ChannelConnectionService $connections): int
    {
        $query = Company::query();

        if ($companyId = $this->option('company')) {
            $query->where('id', $companyId);
        }

        $imported = 0;

        $query->each(function (Company $company) use ($connections, &$imported) {
            $token = (string) $company->getConfig('whatsapp_permanent_access_token', '');
            $phoneId = (string) $company->getConfig('whatsapp_phone_number_id', '');

            if ($token === '' || $phoneId === '') {
                return;
            }

            $connections->ensureWhatsappConnection($company);
            $imported++;
            $this->line("Imported WhatsApp connection for company #{$company->id}");
        });

        $this->info("Imported {$imported} channel connection(s).");

        return self::SUCCESS;
    }
}
