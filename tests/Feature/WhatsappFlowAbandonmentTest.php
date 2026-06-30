<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlowAbandonmentService;
use App\Services\WhatsappFlowSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappFlowAbandonmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_stale_pending_responses_as_abandoned(): void
    {
        config(['whatsapp-flows.abandonment_timeout_hours' => 24]);

        $company = Company::factory()->create();
        $flow = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Test',
            'flow_json' => ['screens' => []],
            'status' => 'draft',
        ]);

        WhatsappFlowResponse::create([
            'company_id' => $company->id,
            'whatsapp_flow_id' => $flow->id,
            'status' => 'pending',
            'sent_at' => now()->subHours(30),
        ]);

        $result = app(WhatsappFlowAbandonmentService::class)->processAbandonedResponses();

        $this->assertSame(1, $result['marked']);
        $this->assertSame('abandoned', WhatsappFlowResponse::first()->status);
    }

    public function test_submission_webhook_is_dispatched_once(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $flow = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Webhook Form',
            'flow_json' => ['screens' => []],
            'status' => 'published',
            'webhook_enabled' => true,
            'webhook_url' => 'https://example.com/hook',
        ]);

        $response = WhatsappFlowResponse::create([
            'company_id' => $company->id,
            'whatsapp_flow_id' => $flow->id,
            'status' => 'pending',
        ]);

        app(WhatsappFlowSubmissionService::class)->handleCompleted($response, ['text_1' => 'Jane'], null, null);

        Queue::assertPushed(\App\Jobs\DispatchWhatsappFlowSubmissionWebhook::class);
        $this->assertNotNull($response->fresh()->webhook_dispatched_at);
    }
}
