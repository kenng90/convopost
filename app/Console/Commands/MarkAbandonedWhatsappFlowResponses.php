<?php

namespace App\Console\Commands;

use App\Services\WhatsappFlowAbandonmentService;
use Illuminate\Console\Command;

class MarkAbandonedWhatsappFlowResponses extends Command
{
    protected $signature = 'whatsapp-flows:mark-abandoned';

    protected $description = 'Mark stale pending WhatsApp form responses as abandoned and resume automations on the else path';

    public function handle(WhatsappFlowAbandonmentService $service): int
    {
        $result = $service->processAbandonedResponses();

        $this->info("Marked {$result['marked']} response(s) as abandoned. Resumed {$result['resumed']} automation(s).");

        return self::SUCCESS;
    }
}
