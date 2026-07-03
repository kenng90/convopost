<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use App\Services\VoiceBooking\VoiceBookingContextService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Whatsappcall\Support\CallContactResolver;
use Modules\Wpbox\Models\Contact;

class AiCallDispatchService
{
    public function __construct(
        protected WhatsappAgentContextService $contextService,
        protected VoiceBookingContextService $bookingContextService,
        protected CompanyVoiceOpenAiKeyResolver $openAiKeyResolver,
    ) {
    }

    public function dispatch(CallModel $call, Company $company, array $webhookValue): bool
    {
        $defaultWorkerUrl = config('whatsappcall.ai_worker_url', '')
            ?: config('whatsappcallworker.default_url', 'http://127.0.0.1:8787');

        $workerUrl = rtrim(
            $company->getConfig('whatsapp_ai_worker_url', $defaultWorkerUrl),
            '/'
        );

        if ($workerUrl === '') {
            Log::warning('AiCallDispatchService: no worker URL configured', [
                'call_id' => $call->id,
                'company_id' => $company->id,
            ]);

            return false;
        }

        $waCallId = data_get($webhookValue, 'calls.0.id')
            ?? data_get($webhookValue, 'calls.0.call_id')
            ?? data_get($webhookValue, 'call.id')
            ?? $call->wa_call_id;

        if (! $waCallId) {
            Log::warning('AiCallDispatchService: webhook missing WhatsApp call id', [
                'call_id' => $call->id,
                'meta_keys' => array_keys($webhookValue),
                'calls0_keys' => array_keys(data_get($webhookValue, 'calls.0', []) ?: []),
            ]);
        }

        if ($waCallId && $call->wa_call_id !== $waCallId) {
            $call->update([
                'wa_call_id' => $waCallId,
                'meta' => $webhookValue,
            ]);
            $call->refresh();
        } elseif (! $call->meta) {
            $call->update(['meta' => $webhookValue]);
            $call->refresh();
        }

        $offerSdp = data_get($webhookValue, 'calls.0.session.sdp');
        $offerType = data_get($webhookValue, 'calls.0.session.sdp_type', 'offer');

        $requiredFields = json_decode(
            $company->getConfig('whatsapp_ai_required_fields', '[]'),
            true
        ) ?: [];

        $openAiKey = $this->openAiKeyResolver->resolve($company);
        if (! $openAiKey) {
            Log::error('AiCallDispatchService: no company OpenAI API key — add under WhatsApp Calling → AI voice settings', [
                'call_id' => $call->id,
                'company_id' => $company->id,
            ]);

            return false;
        }

        $context = $this->contextService->buildForCompany(
            $company,
            $this->contextService->defaultVectorQuery($company)
        );

        $booking = $this->bookingContextService->buildForCompany($company);
        $systemContext = trim(($context['system_context'] ?? '')."\n\n".($booking['booking_context'] ?? ''));

        $contact = $this->resolveDispatchContact($call, $company);

        $payload = [
            'call_id' => $call->id,
            'wa_call_id' => $waCallId,
            'company_id' => $company->id,
            'contact_id' => $contact?->id ?? $call->contact_id,
            'contact_name' => $contact?->name ? trim((string) $contact->name) : null,
            'wa_user_id' => $call->wa_user_id,
            'offer' => [
                'type' => $offerType,
                'sdp' => $offerSdp,
            ],
            'required_field_keys' => $requiredFields,
            'ai_greeting' => $company->getConfig('whatsapp_ai_greeting', ''),
            'handoff_phrases' => json_decode($company->getConfig('whatsapp_ai_handoff_phrases', '[]'), true) ?: [],
            'system_context' => $systemContext,
            'vector_context' => $context['vector_context'],
            'flow_id' => $context['flow_id'],
            'voice_booking' => $booking['voice_booking'],
            'openai_api_key' => $openAiKey,
            'worker_callback_base' => rtrim(config('whatsappcall.laravel_callback_url', 'http://127.0.0.1:8000'), '/').'/api/whatsappcall/worker',
            'laravel_base_url' => rtrim(config('whatsappcall.laravel_callback_url', 'http://127.0.0.1:8000'), '/'),
            'meta' => $webhookValue,
        ];

        $secret = $company->getConfig(
            'whatsapp_ai_worker_secret',
            config('whatsappcall.ai_worker_secret', '')
        );
        $payload['callback_secret'] = $secret;

        try {
            $health = $this->probeWorkerHealth($workerUrl, $secret);
            if ($health !== null) {
                Log::info('AiCallDispatchService: worker health', [
                    'call_id' => $call->id,
                    'worker_url' => $workerUrl,
                    'version' => $health['version'] ?? null,
                    'mode' => $health['mode'] ?? null,
                    'openai_configured' => $health['openai_configured'] ?? null,
                ]);

                if (($health['mode'] ?? '') === 'stub') {
                    Log::warning('AiCallDispatchService: worker is in STUB mode — caller will hear silence. Restart worker: php artisan whatsappcall:worker --install', [
                        'call_id' => $call->id,
                    ]);
                }
            } else {
                Log::warning('AiCallDispatchService: worker health check failed — is php artisan whatsappcall:worker running?', [
                    'call_id' => $call->id,
                    'worker_url' => $workerUrl,
                ]);
            }

            // Worker returns 202 immediately; full WebRTC connect runs in background.
            $request = Http::timeout(5)->connectTimeout(3);
            if ($secret) {
                $request = $request->withHeaders(['X-Worker-Secret' => $secret]);
            }

            $response = $request->post($workerUrl.'/incoming', $payload);

            if ($response->failed()) {
                Log::error('AiCallDispatchService: worker rejected dispatch', [
                    'call_id' => $call->id,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }

            $call->update([
                'handled_by_type' => 'ai',
                'status' => 'ai_dispatched',
                'ai_session_id' => $response->json('session_id') ?? $call->ai_session_id,
            ]);

            Log::info('AiCallDispatchService: worker accepted dispatch', [
                'call_id' => $call->id,
                'company_id' => $company->id,
                'worker_url' => $workerUrl,
                'worker_version' => $response->json('version'),
                'worker_mode' => $response->json('worker_mode'),
                'openai_configured' => $response->json('openai_configured'),
                'openai_key_source' => 'company',
                'session_id' => $response->json('session_id'),
                'flow_id' => $context['flow_id'],
                'system_context_chars' => strlen($context['system_context'] ?? ''),
            ]);

            if ($response->json('worker_mode') === null && $response->json('version') === null) {
                Log::warning('AiCallDispatchService: worker response missing version/mode — restart worker with latest code: php artisan whatsappcall:worker --install', [
                    'call_id' => $call->id,
                ]);
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('AiCallDispatchService: dispatch exception', [
                'call_id' => $call->id,
                'message' => $th->getMessage(),
            ]);

            return false;
        }
    }

    protected function resolveDispatchContact(CallModel $call, Company $company): ?Contact
    {
        if ($call->contact_id) {
            $contact = Contact::withoutGlobalScopes()
                ->where('id', $call->contact_id)
                ->where('company_id', $company->id)
                ->first();
            if ($contact) {
                return $contact;
            }
        }

        return CallContactResolver::findByPhone((int) $company->id, $call->wa_user_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function probeWorkerHealth(string $workerUrl, ?string $secret): ?array
    {
        try {
            $request = Http::timeout(2)->connectTimeout(1);
            if ($secret) {
                $request = $request->withHeaders(['X-Worker-Secret' => $secret]);
            }

            $response = $request->get($workerUrl.'/health');
            if ($response->failed()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            return $json;
        } catch (\Throwable) {
            return null;
        }
    }
}
