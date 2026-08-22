<?php

namespace Modules\Voicecall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Telephony\TelephonyProvider;
use App\Services\Telephony\Voice\TelnyxCallControl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Voicecall\Models\VoiceCall;
use Modules\Voicecall\Models\VoicePhoneNumber;
use Modules\Voicecall\Services\VoiceAgentContextService;
use Modules\Voicecall\Services\VoiceCallCompletionService;
use Modules\Voicecall\Traits\VoiceInboundHelpers;

class TelnyxWebhookController extends Controller
{
    use VoiceInboundHelpers;

    public function __construct(
        protected VoiceAgentContextService $contextService,
        protected VoiceCallCompletionService $completionService,
    ) {
    }

    public function handle(Request $request): Response
    {
        $eventType = (string) ($request->input('data.event_type') ?? $request->input('event_type') ?? '');
        $payload = $request->input('data.payload', $request->input('payload', []));
        if (! is_array($payload)) {
            $payload = [];
        }

        $callControlId = (string) ($payload['call_control_id'] ?? '');
        if ($callControlId === '') {
            return response('', 200);
        }

        return match ($eventType) {
            'call.initiated' => $this->onInitiated($payload, $callControlId),
            'call.answered' => $this->onAnswered($payload, $callControlId),
            'call.speak.ended' => $this->onSpeakEnded($callControlId),
            'call.gather.ended' => $this->onGatherEnded($payload, $callControlId),
            'call.hangup' => $this->onHangup($payload, $callControlId),
            default => response('', 200),
        };
    }

    private function onInitiated(array $payload, string $callControlId): Response
    {
        $direction = strtolower((string) ($payload['direction'] ?? ''));
        if ($direction !== '' && $direction !== 'incoming') {
            return response('', 200);
        }

        $to = (string) ($payload['to'] ?? '');
        $line = $this->resolveLine($to);
        if (! $line) {
            Log::warning('telnyx.voice_line_not_found', ['to' => $to]);

            return response('', 200);
        }

        $company = Company::find($line->company_id);
        if (! $company) {
            return response('', 200);
        }

        $from = (string) ($payload['from'] ?? '');
        $contact = $this->findOrCreateContact($company, $from);

        $existing = VoiceCall::where('provider_call_id', $callControlId)->first();
        if (! $existing) {
            VoiceCall::create([
                'company_id' => $company->id,
                'provider' => TelephonyProvider::TELNYX,
                'voice_phone_number_id' => $line->id,
                'contact_id' => $contact?->id,
                'provider_call_id' => $callControlId,
                'from_number' => $from,
                'to_number' => $to,
                'direction' => 'inbound',
                'status' => 'initiated',
                'started_at' => now(),
                'structured' => [
                    'channel' => 'telnyx_voice',
                    'telnyx_stage' => 'initiated',
                    'context_excerpt' => mb_substr($this->contextService->buildSystemContext($line, $company), 0, 2000),
                ],
            ]);
        }

        TelnyxCallControl::forCompany($company)->answer($callControlId);

        return response('', 200);
    }

    private function onAnswered(array $payload, string $callControlId): Response
    {
        $voiceCall = VoiceCall::where('provider_call_id', $callControlId)->first();
        if (! $voiceCall) {
            return response('', 200);
        }

        $line = VoicePhoneNumber::withoutGlobalScopes()->find($voiceCall->voice_phone_number_id);
        $company = Company::find($voiceCall->company_id);
        if (! $line || ! $company) {
            return response('', 200);
        }

        $stage = $voiceCall->structured['telnyx_stage'] ?? '';
        if ($stage === 'greeting_spoken' || $stage === 'done') {
            return response('', 200);
        }

        $greeting = trim($line->ai_greeting ?? '') ?: config('voicecall.default_greeting');
        TelnyxCallControl::forCompany($company)->speak($callControlId, $greeting);

        $voiceCall->update([
            'status' => 'in-progress',
            'structured' => array_merge($voiceCall->structured ?? [], ['telnyx_stage' => 'greeting_spoken']),
        ]);

        return response('', 200);
    }

    private function onSpeakEnded(string $callControlId): Response
    {
        $voiceCall = VoiceCall::where('provider_call_id', $callControlId)->first();
        if (! $voiceCall) {
            return response('', 200);
        }

        $stage = $voiceCall->structured['telnyx_stage'] ?? '';
        if ($stage === 'gathering' || $stage === 'done') {
            return response('', 200);
        }

        $line = VoicePhoneNumber::withoutGlobalScopes()->find($voiceCall->voice_phone_number_id);
        $company = Company::find($voiceCall->company_id);
        if (! $line || ! $company) {
            return response('', 200);
        }

        if ($stage !== 'greeting_spoken') {
            return response('', 200);
        }

        TelnyxCallControl::forCompany($company)->gatherUsingSpeak(
            $callControlId,
            'Please tell me how I can help you.',
        );

        $voiceCall->update([
            'structured' => array_merge($voiceCall->structured ?? [], ['telnyx_stage' => 'gathering']),
        ]);

        return response('', 200);
    }

    private function onGatherEnded(array $payload, string $callControlId): Response
    {
        $voiceCall = VoiceCall::where('provider_call_id', $callControlId)->first();
        if (! $voiceCall) {
            return response('', 200);
        }

        if (($voiceCall->structured['telnyx_stage'] ?? '') === 'done') {
            return response('', 200);
        }

        $line = VoicePhoneNumber::withoutGlobalScopes()->find($voiceCall->voice_phone_number_id);
        $company = Company::find($voiceCall->company_id);
        $speech = $this->extractTelnyxSpeech($payload);
        $handoff = $this->detectHandoff($speech, $line?->handoff_phrases ?? []);

        $voiceCall->load('contact');
        $transcript = ($voiceCall->transcript ?? '')."[Caller] {$speech}\n";
        $turns = (int) ($voiceCall->structured['agent_turns'] ?? 0) + 1;
        $maxTurns = 8;

        $agentReply = '';
        $agentHandoff = $handoff;
        $contact = $voiceCall->contact;

        if ($company && $contact && ! $handoff) {
            $result = app(\App\Services\Agents\ActionAgentService::class)
                ->replyForVoice($company, $contact, $speech, (string) $voiceCall->transcript);
            $agentReply = $result['reply'];
            $agentHandoff = $result['handoff'] || $handoff;
            $transcript .= '[Agent] '.$agentReply."\n";
        }

        $shouldFinish = $agentHandoff || $turns >= $maxTurns || $agentReply === '';

        $voiceCall->update([
            'transcript' => $transcript,
            'handoff_requested' => $agentHandoff,
            'handoff_reason' => $agentHandoff ? 'Caller requested a human agent' : null,
            'structured' => array_merge($voiceCall->structured ?? [], [
                'channel' => 'telnyx_voice',
                'telnyx_stage' => $shouldFinish ? 'done' : 'gathering',
                'agent_turns' => $turns,
                'intent' => $agentHandoff ? 'handoff' : 'phone_inquiry',
                'summary_bullets' => [
                    'Inbound Telnyx voice call',
                    $speech ? 'Caller said: '.mb_substr($speech, 0, 120) : 'No speech captured',
                ],
                'fields' => $this->stubFields($line, $voiceCall),
                'handoff_requested' => $agentHandoff,
                'shared_brain' => true,
            ]),
            'duration_seconds' => $voiceCall->started_at
                ? max(1, now()->diffInSeconds($voiceCall->started_at))
                : 15,
            'ended_at' => $shouldFinish ? now() : $voiceCall->ended_at,
        ]);

        $control = $company ? TelnyxCallControl::forCompany($company) : null;

        if (! $shouldFinish && $agentReply !== '' && $control) {
            $control->gatherUsingSpeak($callControlId, $agentReply);

            return response('', 200);
        }

        if ($line && $company) {
            $this->completionService->completeFromVoiceCall($voiceCall->fresh(), $line);
        }

        $closing = $agentHandoff
            ? ($agentReply !== '' ? $agentReply : 'Thank you. A team member will follow up with you in chat shortly.')
            : ($agentReply !== '' ? $agentReply.' Goodbye.' : 'Thank you for calling. Goodbye.');

        if ($control) {
            $control->speak($callControlId, $closing);
            $control->hangup($callControlId);
        }

        return response('', 200);
    }

    private function onHangup(array $payload, string $callControlId): Response
    {
        $cause = (string) ($payload['hangup_cause'] ?? 'completed');
        VoiceCall::where('provider_call_id', $callControlId)->update([
            'status' => $cause,
            'ended_at' => now(),
        ]);

        return response('', 200);
    }
}
