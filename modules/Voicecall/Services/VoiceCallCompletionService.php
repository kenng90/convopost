<?php

namespace Modules\Voicecall\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Voicecall\Models\VoiceCall;
use Modules\Voicecall\Models\VoicePhoneNumber;
use Modules\Whatsappcall\Services\CallBriefService;
use App\Services\VoiceBooking\VoiceCallBookingService;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Models\Contact;

class VoiceCallCompletionService
{
    public function __construct(
        protected CallBriefService $briefService,
    ) {
    }

    public function completeFromVoiceCall(VoiceCall $voiceCall, VoicePhoneNumber $line, array $extra = []): void
    {
        $company = Company::find($voiceCall->company_id);
        if (! $company) {
            return;
        }

        $contact = $this->resolveContact($voiceCall, $company);
        if (! $contact) {
            Log::warning('VoiceCallCompletionService: no contact for brief', [
                'voice_call_id' => $voiceCall->id,
                'from' => $voiceCall->from_number,
            ]);

            return;
        }

        $structured = $voiceCall->structured ?? [];
        $structured['handled_by'] = 'ai';
        $structured['duration_seconds'] = $voiceCall->duration_seconds;
        $structured['handoff_requested'] = $voiceCall->handoff_requested;
        $structured['handoff_reason'] = $voiceCall->handoff_reason;
        if (empty($structured['required_field_keys'])) {
            $structured['required_field_keys'] = $line->required_field_keys ?? [];
        }

        $payload = $this->briefService->normalizeStructuredPayload($structured, [
            'transcript' => $voiceCall->transcript,
            'duration_seconds' => $voiceCall->duration_seconds,
            'handoff_requested' => $voiceCall->handoff_requested,
            'handoff_reason' => $voiceCall->handoff_reason,
        ]);

        if ($voiceCall->id) {
            $payload = app(VoiceCallBookingService::class)->mergeBookingResultsIntoStructured($payload);
        }

        $message = $contact->addCallBrief($payload, null);
        $message->update(['call_id' => null]);

        $voiceCall->update([
            'status' => 'completed',
            'brief_message_id' => $message->id,
            'structured' => $payload,
            'ended_at' => $voiceCall->ended_at ?? now(),
        ]);

        if ($voiceCall->handoff_requested) {
            $contact->voice_handoff_pending = true;
            $contact->has_chat = true;
            $contact->is_last_message_by_contact = true;
            $contact->last_reply_at = now();
            $contact->last_message = $contact->trimString(__('Phone call — needs agent'), 40);
            $contact->save();
        }

        try {
            event(new Chatlistchange($contact->id, $contact->company_id));
        } catch (\Throwable $th) {
            Log::warning('VoiceCallCompletionService: Chatlistchange failed', ['e' => $th->getMessage()]);
        }
    }

    private function resolveContact(VoiceCall $voiceCall, Company $company): ?Contact
    {
        if ($voiceCall->contact_id) {
            $contact = Contact::where('id', $voiceCall->contact_id)
                ->where('company_id', $company->id)
                ->first();
            if ($contact) {
                return $contact;
            }
        }

        $from = $voiceCall->from_number;
        if (! $from) {
            return null;
        }

        return Contact::where('company_id', $company->id)
            ->where(function ($q) use ($from) {
                $q->where('phone', $from)
                    ->orWhere('phone', '+'.$from)
                    ->orWhere('phone', ltrim($from, '+'));
            })
            ->first();
    }
}
