<?php

namespace Modules\Whatsappcall\Console;

use Illuminate\Console\Command;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Whatsappcall\Services\CallBriefService;

class SimulateAiCallComplete extends Command
{
    protected $signature = 'whatsappcall:simulate-ai-complete {call_id : Internal call ID} {--handoff : Mark as handoff requested}';

    protected $description = 'Simulate AI worker completing a call (development / testing)';

    public function handle(CallBriefService $briefService): int
    {
        $call = CallModel::findOrFail($this->argument('call_id'));

        $briefService->completeAiCall($call, [
            'duration_seconds' => 142,
            'handoff_requested' => $this->option('handoff'),
            'handoff_reason' => $this->option('handoff') ? 'Customer asked to speak with a person' : null,
            'transcript' => 'Sample transcript for testing.',
            'structured' => [
                'intent' => 'support',
                'urgency' => 'medium',
                'summary_bullets' => [
                    'Customer reports damaged item on order #8821',
                    'Requested human agent before ending call',
                ],
                'fields' => [
                    [
                        'key' => 'name',
                        'label' => 'Name',
                        'value' => 'Jane Doe',
                        'status' => 'confirmed',
                        'source_quote' => 'My name is Jane Doe',
                    ],
                    [
                        'key' => 'email',
                        'label' => 'Email',
                        'value' => 'jane@example.com',
                        'status' => 'confirmed',
                        'source_quote' => 'jane at example dot com',
                    ],
                    [
                        'key' => 'order_id',
                        'label' => 'Order ID',
                        'value' => '8821',
                        'status' => 'confirmed',
                        'source_quote' => 'order number is 8821',
                    ],
                ],
                'missing_required' => ['phone'],
                'handoff_requested' => $this->option('handoff'),
                'handoff_reason' => $this->option('handoff') ? 'Customer asked to speak with a person' : null,
            ],
        ]);

        $this->info('Call brief posted for call #'.$call->id);

        return self::SUCCESS;
    }
}
