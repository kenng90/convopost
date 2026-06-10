<?php

namespace Modules\Voicecall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Telephony\TelephonyProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Voicecall\Models\VoiceCall;
use Modules\Voicecall\Models\VoicePhoneNumber;
use Modules\Voicecall\Services\VoiceAgentContextService;
use Modules\Voicecall\Services\VoiceCallCompletionService;
use Modules\Voicecall\Traits\VoiceInboundHelpers;

class TwilioWebhookController extends Controller
{
    use VoiceInboundHelpers;

    public function __construct(
        protected VoiceAgentContextService $contextService,
        protected VoiceCallCompletionService $completionService,
    ) {
    }

    public function incoming(Request $request): Response
    {
        $line = $this->resolveLine($request->input('To'));
        if (! $line) {
            return $this->twiml('<Response><Say>Number not configured.</Say><Hangup/></Response>');
        }

        $company = Company::find($line->company_id);
        $from = $request->input('From', '');
        $callSid = $request->input('CallSid', '');

        $contact = $this->findOrCreateContact($company, $from);

        $voiceCall = VoiceCall::create([
            'company_id' => $company->id,
            'provider' => TelephonyProvider::TWILIO,
            'voice_phone_number_id' => $line->id,
            'contact_id' => $contact?->id,
            'twilio_call_sid' => $callSid,
            'provider_call_id' => $callSid,
            'from_number' => $from,
            'to_number' => $request->input('To'),
            'direction' => 'inbound',
            'status' => 'in-progress',
            'started_at' => now(),
            'structured' => [
                'channel' => 'twilio_voice',
                'context_excerpt' => mb_substr($this->contextService->buildSystemContext($line, $company), 0, 2000),
            ],
        ]);

        $greeting = trim($line->ai_greeting ?? '') ?: config('voicecall.default_greeting');
        $gatherUrl = route('voicecall.webhook.gather', ['voiceCall' => $voiceCall->id]);

        $twiml = '<Response>'
            .'<Say voice="Polly.Joanna">'.$this->xmlEscape($greeting).'</Say>'
            .'<Gather input="speech" action="'.$this->xmlEscape($gatherUrl).'" method="POST" speechTimeout="auto" timeout="5">'
            .'<Say voice="Polly.Joanna">Please tell me how I can help you.</Say>'
            .'</Gather>'
            .'<Say voice="Polly.Joanna">I did not hear anything. Goodbye.</Say>'
            .'<Hangup/>'
            .'</Response>';

        return $this->twiml($twiml);
    }

    public function gather(Request $request, VoiceCall $voiceCall): Response
    {
        $line = VoicePhoneNumber::withoutGlobalScopes()->find($voiceCall->voice_phone_number_id);
        $company = Company::find($voiceCall->company_id);
        $speech = trim($request->input('SpeechResult', ''));

        $transcript = "[Caller] {$speech}\n";
        $handoff = $this->detectHandoff($speech, $line?->handoff_phrases ?? []);

        $voiceCall->load('contact');
        $voiceCall->update([
            'transcript' => ($voiceCall->transcript ?? '').$transcript,
            'handoff_requested' => $handoff,
            'handoff_reason' => $handoff ? 'Caller requested a human agent' : null,
            'structured' => array_merge($voiceCall->structured ?? [], [
                'intent' => 'phone_inquiry',
                'summary_bullets' => [
                    'Inbound Twilio voice call',
                    $speech ? 'Caller said: '.mb_substr($speech, 0, 120) : 'No speech captured',
                ],
                'fields' => $this->stubFields($line, $voiceCall),
                'handoff_requested' => $handoff,
            ]),
            'duration_seconds' => $voiceCall->started_at
                ? max(1, now()->diffInSeconds($voiceCall->started_at))
                : 15,
            'ended_at' => now(),
        ]);

        if ($line && $company) {
            $this->completionService->completeFromVoiceCall($voiceCall, $line);
        }

        $closing = $handoff
            ? 'Thank you. A team member will follow up with you in chat shortly.'
            : 'Thank you for calling. Goodbye.';

        return $this->twiml('<Response><Say voice="Polly.Joanna">'.$this->xmlEscape($closing).'</Say><Hangup/></Response>');
    }

    public function status(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $status = $request->input('CallStatus');
        if ($callSid && $status) {
            VoiceCall::where(function ($q) use ($callSid) {
                $q->where('provider_call_id', $callSid)->orWhere('twilio_call_sid', $callSid);
            })->update(['status' => $status]);
        }

        return response('', 204);
    }

    private function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function twiml(string $xml): Response
    {
        return response($xml, 200)->header('Content-Type', 'text/xml');
    }
}
