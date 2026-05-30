<?php

namespace Modules\Whatsappcall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Whatsappcall\Services\CallBriefService;

class CallWorkerController extends Controller
{
    use \Modules\Whatsappcall\Traits\WhatsappCall;

    public function preAccept(Request $request, CallModel $call)
    {
        $call = $this->resolveWorkerCall($call);

        $validated = $request->validate([
            'sdp' => 'required|string',
            'wa_call_id' => 'nullable|string',
        ]);

        $this->syncWaCallIdFromWorker($call, $validated['wa_call_id'] ?? null);

        return $this->proxyMetaCallAction($call, 'pre_accept', $validated['sdp']);
    }

    public function accept(Request $request, CallModel $call)
    {
        $call = $this->resolveWorkerCall($call);

        $validated = $request->validate([
            'sdp' => 'required|string',
            'wa_call_id' => 'nullable|string',
        ]);

        $this->syncWaCallIdFromWorker($call, $validated['wa_call_id'] ?? null);

        $response = $this->proxyMetaCallAction($call, 'accept', $validated['sdp']);

        if ($response->getStatusCode() === 200) {
            $call->update([
                'status' => 'accept',
                'answered_at' => now(),
                'handled_by_type' => 'ai',
            ]);
        }

        return $response;
    }

    public function terminate(Request $request, CallModel $call)
    {
        $call = $this->resolveWorkerCall($call);

        $response = $this->proxyMetaCallAction($call, 'terminate', null);

        if ($response->getStatusCode() === 200) {
            $call->update(['status' => 'terminate', 'ended_at' => now()]);
        }

        return $response;
    }

    public function complete(Request $request, CallModel $call, CallBriefService $briefService)
    {
        $call = $this->resolveWorkerCall($call);

        Log::info('CallWorkerController: complete requested', [
            'call_id' => $call->id,
            'company_id' => $call->company_id,
            'contact_id' => $call->contact_id,
            'existing_brief_message_id' => $call->brief_message_id,
            'duration_seconds' => $request->input('duration_seconds'),
            'has_transcript' => $request->filled('transcript'),
        ]);

        if ($call->brief_message_id) {
            return response()->json([
                'ok' => true,
                'call_id' => $call->id,
                'brief_message_id' => $call->brief_message_id,
                'duplicate' => true,
            ]);
        }

        $validated = $request->validate([
            'duration_seconds' => 'nullable|integer|min:0',
            'transcript' => 'nullable|string',
            'handoff_requested' => 'nullable|boolean',
            'handoff_reason' => 'nullable|string',
            'intent' => 'nullable|string',
            'structured' => 'nullable|array',
            'summary_bullets' => 'nullable|array',
            'fields' => 'nullable|array',
            'missing_required' => 'nullable|array',
            'ai_session_id' => 'nullable|string',
            'worker_debug' => 'nullable|array',
        ]);

        $workerDebug = $validated['worker_debug'] ?? null;
        if (is_array($workerDebug)) {
            Log::info('CallWorkerController: worker session debug', [
                'call_id' => $call->id,
                'session_type' => $workerDebug['session_type'] ?? null,
                'worker_mode' => $workerDebug['worker_mode'] ?? null,
                'openai_configured' => $workerDebug['openai_configured'] ?? null,
                'openai_connected' => $workerDebug['openai_connected'] ?? null,
                'openai_session_ready' => $workerDebug['openai_session_ready'] ?? null,
                'initial_greeting_sent' => $workerDebug['initial_greeting_sent'] ?? null,
                'remote_track_received' => $workerDebug['remote_track_received'] ?? null,
                'audio_in_frames' => $workerDebug['audio_in_frames'] ?? null,
                'audio_out_chunks' => $workerDebug['audio_out_chunks'] ?? null,
                'end_reason' => $workerDebug['end_reason'] ?? null,
                'errors' => $workerDebug['errors'] ?? [],
                'warnings' => $workerDebug['warnings'] ?? [],
                'openai_event_counts' => $workerDebug['openai_event_counts'] ?? [],
            ]);

            if (($workerDebug['session_type'] ?? '') === 'stub') {
                Log::warning('CallWorkerController: call ran in STUB mode — no spoken AI. Set OPENAI_API_KEY and WHATSAPP_AI_WORKER_MODE=realtime on the worker.', [
                    'call_id' => $call->id,
                ]);
            }

            if (($workerDebug['audio_out_chunks'] ?? 0) === 0 && ($workerDebug['session_type'] ?? '') === 'realtime') {
                Log::warning('CallWorkerController: Realtime session produced zero audio chunks — check OpenAI API key, billing, and model access.', [
                    'call_id' => $call->id,
                    'openai_model' => $workerDebug['openai_model'] ?? null,
                ]);
            }
        } else {
            $duration = $validated['duration_seconds'] ?? null;
            $summary = $validated['summary_bullets'][0]
                ?? data_get($validated, 'structured.summary_bullets.0');
            $likelyStub = is_int($duration) && $duration >= 14 && $duration <= 20;

            Log::warning('CallWorkerController: worker_debug missing from completion payload — restart worker: php artisan whatsappcall:worker --install', [
                'call_id' => $call->id,
                'duration_seconds' => $duration,
                'likely_stub_mode' => $likelyStub,
                'summary_preview' => is_string($summary) ? mb_substr($summary, 0, 120) : null,
            ]);
        }

        $structured = $validated['structured'] ?? $validated;
        $structured['fields'] = $structured['fields'] ?? $validated['fields'] ?? [];
        $structured['summary_bullets'] = $structured['summary_bullets'] ?? $validated['summary_bullets'] ?? [];
        $structured['intent'] = $structured['intent'] ?? $validated['intent'] ?? null;
        $structured['handoff_requested'] = $validated['handoff_requested'] ?? $structured['handoff_requested'] ?? false;
        $structured['handoff_reason'] = $validated['handoff_reason'] ?? $structured['handoff_reason'] ?? null;
        if (is_array($workerDebug)) {
            $structured['worker_debug'] = $workerDebug;
        }

        $call = $briefService->completeAiCall($call, array_merge($validated, [
            'structured' => $structured,
        ]));

        Log::info('CallWorkerController: AI call completed', [
            'call_id' => $call->id,
            'brief_message_id' => $call->brief_message_id,
            'handoff_requested' => $call->handoff_requested,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'session_type' => is_array($workerDebug) ? ($workerDebug['session_type'] ?? null) : null,
        ]);

        return response()->json([
            'ok' => true,
            'call_id' => $call->id,
            'brief_message_id' => $call->brief_message_id,
        ]);
    }

    public function show(CallModel $call)
    {
        $call = $this->resolveWorkerCall($call);

        $company = \App\Models\Company::find($call->company_id);

        return response()->json([
            'ok' => true,
            'call' => $call,
            'company' => [
                'id' => $company?->id,
                'whatsapp_phone_number_id' => $company?->getConfig('whatsapp_phone_number_id', ''),
            ],
        ]);
    }

    /**
     * Module routes may run without SubstituteBindings; ensure we load the DB row.
     */
    private function resolveWorkerCall(CallModel $call): CallModel
    {
        if ($call->exists && $call->getKey()) {
            return $call->fresh() ?? $call;
        }

        $routeKey = request()->route('call');
        if ($routeKey instanceof CallModel && $routeKey->exists) {
            return $routeKey->fresh() ?? $routeKey;
        }

        $id = is_numeric($routeKey) ? (int) $routeKey : (is_numeric($call->getKey()) ? (int) $call->getKey() : null);

        if (! $id) {
            abort(404, 'Call not found');
        }

        return CallModel::findOrFail($id);
    }

    private function syncWaCallIdFromWorker(CallModel $call, ?string $waCallId): void
    {
        if (! $waCallId || $call->wa_call_id === $waCallId) {
            return;
        }

        $call->update(['wa_call_id' => $waCallId]);
        $call->refresh();
    }

    private function proxyMetaCallAction(CallModel $call, string $action, ?string $sdp)
    {
        $call->refresh();
        $waCallId = $call->resolveWaCallId();
        if (! $waCallId) {
            Log::warning('CallWorkerController: missing wa_call_id', [
                'call_id' => $call->id,
                'meta_keys' => is_array($call->meta) ? array_keys($call->meta) : [],
            ]);

            return response()->json(['ok' => false, 'error' => 'No WhatsApp call id'], 422);
        }

        $company = \App\Models\Company::findOrFail($call->company_id);
        $phoneId = $company->getConfig('whatsapp_phone_number_id', '');
        $token = $company->getConfig('whatsapp_permanent_access_token', '');

        if (! $phoneId || ! $token) {
            return response()->json(['ok' => false, 'error' => 'Phone ID or token missing'], 422);
        }

        $url = self::$facebookAPI.$phoneId.'/calls';
        $payload = [
            'messaging_product' => 'whatsapp',
            'call_id' => $waCallId,
            'action' => $action,
        ];

        if ($sdp !== null) {
            $payload['session'] = [
                'sdp_type' => 'answer',
                'sdp' => $sdp,
            ];
        }

        $res = \Illuminate\Support\Facades\Http::withToken($token)->post($url, $payload);

        if ($res->failed()) {
            return response()->json([
                'ok' => false,
                'status' => $res->status(),
                'body' => $res->json() ?? $res->body(),
            ], $res->status());
        }

        return response()->json(['ok' => true, 'success' => true]);
    }
}
