<?php

namespace App\Jobs;

use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlowResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWhatsappFlowSubmissionWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $responseData
     */
    public function __construct(
        public WhatsappFlowResponse $flowResponse,
        public array $responseData = []
    ) {
    }

    public function handle(WhatsappFlowResponseService $responseService): void
    {
        $flow = $this->flowResponse->whatsappFlow;
        if (! $flow || ! $flow->webhook_enabled || empty($flow->webhook_url)) {
            return;
        }

        $cleanData = $responseService->cleanResponses($this->responseData);
        $enriched = $responseService->enrichResponsesFlat($cleanData, $flow);

        $payload = [
            'event' => 'form.submitted',
            'submitted_at' => now()->toIso8601String(),
            'form' => [
                'id' => $flow->id,
                'name' => $flow->name,
                'meta_flow_id' => $flow->meta_flow_id,
            ],
            'submission' => [
                'id' => $this->flowResponse->id,
                'status' => $this->flowResponse->status,
                'sent_at' => $this->flowResponse->sent_at?->toIso8601String(),
                'completed_at' => $this->flowResponse->completed_at?->toIso8601String(),
            ],
            'contact' => [
                'id' => $this->flowResponse->contact_id,
                'name' => $this->flowResponse->contact_name,
                'phone' => $this->flowResponse->contact_phone,
            ],
            'responses' => $cleanData,
            'responses_labeled' => collect($enriched)->mapWithKeys(fn ($item) => [
                $item['key'] => [
                    'label' => $item['label'],
                    'value' => $item['value'],
                    'display_value' => $item['display_value'],
                ],
            ])->all(),
        ];

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post($flow->webhook_url, $payload);

            if (! $response->successful()) {
                Log::warning('WhatsApp Form submission webhook failed', [
                    'form_id' => $flow->id,
                    'response_id' => $this->flowResponse->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp Form submission webhook exception', [
                'form_id' => $flow->id,
                'response_id' => $this->flowResponse->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
