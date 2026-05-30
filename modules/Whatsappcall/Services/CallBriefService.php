<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Models\Field;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Models\Contact;

class CallBriefService
{
    public function completeAiCall(CallModel $call, array $data): CallModel
    {
        if ($call->brief_message_id) {
            Log::info('CallBriefService: call already has brief', [
                'call_id' => $call->id,
                'brief_message_id' => $call->brief_message_id,
            ]);

            return $call->fresh() ?? $call;
        }

        $structured = $data['structured'] ?? $data;
        $company = Company::find($call->company_id);
        if ($company && empty($structured['required_field_keys'])) {
            $structured['required_field_keys'] = json_decode(
                $company->getConfig('whatsapp_ai_required_fields', '[]'),
                true
            ) ?: [];
        }
        $payload = $this->normalizeStructuredPayload($structured, $data, $call);

        $call->update([
            'handled_by_type' => 'ai',
            'status' => 'completed',
            'ended_at' => now(),
            'duration_seconds' => $data['duration_seconds'] ?? $call->duration_seconds,
            'intent' => $payload['intent'] ?? null,
            'summary' => $payload['summary'] ?? ($payload['summary_bullets'][0] ?? null),
            'structured' => $payload,
            'transcript' => $data['transcript'] ?? null,
            'handoff_requested' => (bool) ($data['handoff_requested'] ?? $payload['handoff_requested'] ?? false),
            'handoff_reason' => $data['handoff_reason'] ?? $payload['handoff_reason'] ?? null,
            'ai_session_id' => $data['ai_session_id'] ?? $call->ai_session_id,
        ]);

        $contact = $this->resolveContact($call);
        if ($contact) {
            if ($call->contact_id !== $contact->id) {
                $call->update(['contact_id' => $contact->id]);
                $call->refresh();
            }

            $this->applyConfirmedFields($contact, $payload['fields'] ?? []);
            $message = $contact->addCallBrief($payload, $call);
            $call->update(['brief_message_id' => $message->id]);

            Log::info('CallBriefService: call brief posted to chat', [
                'call_id' => $call->id,
                'company_id' => $call->company_id,
                'contact_id' => $contact->id,
                'message_id' => $message->id,
                'summary_bullets' => count($payload['summary_bullets'] ?? []),
            ]);

            if ($call->handoff_requested) {
                $contact->voice_handoff_pending = true;
                $contact->has_chat = true;
                $contact->is_last_message_by_contact = true;
                $contact->last_reply_at = now();
                $contact->last_message = $contact->trimString(__('AI call — needs agent'), 40);
                $contact->save();
            }

            try {
                event(new Chatlistchange($contact->id, $contact->company_id));
            } catch (\Throwable $th) {
                Log::warning('CallBriefService: Chatlistchange failed', ['e' => $th->getMessage()]);
            }
        } else {
            Log::warning('CallBriefService: no contact for call brief — summary not saved to chat', [
                'call_id' => $call->id,
                'company_id' => $call->company_id,
                'contact_id' => $call->contact_id,
                'wa_user_id' => $call->wa_user_id,
            ]);
        }

        $call->refresh();

        return $call;
    }

    public function normalizeStructuredPayload(array $structured, array $data = [], ?CallModel $call = null): array
    {
        $fields = $structured['fields'] ?? [];
        $required = $structured['required_field_keys'] ?? $data['required_field_keys'] ?? [];
        $missing = $structured['missing_required'] ?? [];

        $fields = $this->enrichFieldsFromCallContext($fields, $call, $required);

        if (empty($missing) && ! empty($required) && is_array($fields)) {
            $presentKeys = collect($fields)
                ->filter(fn ($f) => in_array($f['status'] ?? '', ['confirmed', 'corrected'], true))
                ->pluck('key')
                ->all();
            $missing = array_values(array_diff($required, $presentKeys));
        }

        return [
            'intent' => $structured['intent'] ?? $data['intent'] ?? null,
            'urgency' => $structured['urgency'] ?? $data['urgency'] ?? null,
            'summary' => $structured['summary'] ?? $data['summary'] ?? null,
            'summary_bullets' => $structured['summary_bullets'] ?? $data['summary_bullets'] ?? [],
            'fields' => $fields,
            'missing_required' => $missing,
            'required_field_keys' => $required,
            'handoff_requested' => (bool) ($structured['handoff_requested'] ?? $data['handoff_requested'] ?? false),
            'handoff_reason' => $structured['handoff_reason'] ?? $data['handoff_reason'] ?? null,
            'transcript_excerpt' => $structured['transcript_excerpt']
                ?? $data['transcript_excerpt']
                ?? (isset($data['transcript']) ? mb_substr((string) $data['transcript'], 0, 2000) : null),
            'handled_by' => 'ai',
            'duration_seconds' => $data['duration_seconds'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, string>  $required
     * @return array<int, array<string, mixed>>
     */
    protected function enrichFieldsFromCallContext(array $fields, ?CallModel $call, array $required): array
    {
        $byKey = collect($fields)->keyBy(fn ($f) => $f['key'] ?? '')->all();

        $contact = null;
        if ($call) {
            $contact = $this->resolveContact($call);
        }

        if ($call?->wa_user_id && (in_array('phone', $required, true) || empty($required))) {
            $phone = '+'.ltrim((string) $call->wa_user_id, '+');
            if (! isset($byKey['phone']) || empty($byKey['phone']['value'])) {
                $byKey['phone'] = [
                    'key' => 'phone',
                    'label' => 'Phone',
                    'value' => $phone,
                    'status' => 'confirmed',
                ];
            }
        }

        if ($contact?->name && in_array('name', $required, true) && ! isset($byKey['name'])) {
            $byKey['name'] = [
                'key' => 'name',
                'label' => 'Name',
                'value' => $contact->name,
                'status' => 'inferred',
            ];
        }

        if ($contact?->email && in_array('email', $required, true) && ! isset($byKey['email'])) {
            $byKey['email'] = [
                'key' => 'email',
                'label' => 'Email',
                'value' => $contact->email,
                'status' => 'inferred',
            ];
        }

        return array_values(array_filter($byKey, fn ($f) => ($f['key'] ?? '') !== ''));
    }

    public function applyConfirmedFields(Contact $contact, array $fields): void
    {
        foreach ($fields as $field) {
            $status = $field['status'] ?? 'inferred';
            if (! in_array($status, ['confirmed', 'corrected'], true)) {
                continue;
            }

            $key = $field['key'] ?? null;
            $value = $field['value'] ?? null;
            if (! $key || $value === null || $value === '') {
                continue;
            }

            if (in_array($key, ['name', 'email', 'phone'], true)) {
                if (! $this->shouldUpdateStandardContactField($contact, $key, (string) $value, $status)) {
                    continue;
                }

                if ($key === 'name') {
                    $contact->name = $value;
                } elseif ($key === 'email') {
                    $contact->email = $value;
                }
                $contact->save();

                continue;
            }

            $customField = Field::where('company_id', $contact->company_id)
                ->where(function ($q) use ($key) {
                    $q->where('name', $key)->orWhere('id', $key);
                })
                ->first();

            if ($customField) {
                $contact->fields()->syncWithoutDetaching([
                    $customField->id => ['value' => $value],
                ]);
            }
        }
    }

    protected function shouldUpdateStandardContactField(Contact $contact, string $key, string $value, string $status): bool
    {
        if ($this->isPlaceholderContactValue($key, $value)) {
            return false;
        }

        if ($status === 'corrected') {
            return true;
        }

        $current = match ($key) {
            'name' => trim((string) ($contact->name ?? '')),
            'email' => trim((string) ($contact->email ?? '')),
            'phone' => trim((string) ($contact->phone ?? '')),
            default => '',
        };

        if ($current === '') {
            return true;
        }

        if ($key === 'phone') {
            $normalizedCurrent = ltrim($current, '+');
            $normalizedNew = ltrim($value, '+');

            return $normalizedCurrent !== $normalizedNew;
        }

        return false;
    }

    protected function isPlaceholderContactValue(string $key, string $value): bool
    {
        if ($key !== 'name') {
            return false;
        }

        return in_array(strtolower(trim($value)), [
            'voice caller',
            'phone caller',
            'unknown',
            'whatsapp caller',
            'caller',
        ], true);
    }

    private function resolveContact(CallModel $call): ?Contact
    {
        if (! $call->company_id) {
            return null;
        }

        if ($call->contact_id) {
            $contact = Contact::withoutGlobalScopes()
                ->where('id', $call->contact_id)
                ->where('company_id', $call->company_id)
                ->first();
            if ($contact) {
                return $contact;
            }
        }

        return CallContactResolver::findByPhone((int) $call->company_id, $call->wa_user_id);
    }
}
