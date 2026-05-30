<?php

namespace Modules\Voicecall\Traits;

use App\Models\Company;
use Modules\Voicecall\Models\VoiceCall;
use Modules\Voicecall\Models\VoicePhoneNumber;
use Modules\Wpbox\Models\Contact;

trait VoiceInboundHelpers
{
    protected function resolveLine(?string $to): ?VoicePhoneNumber
    {
        if (! $to) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', $to);

        return VoicePhoneNumber::withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) use ($normalized, $to) {
                $q->where('phone_number', $to)
                    ->orWhere('phone_number', $normalized)
                    ->orWhere('phone_number', ltrim($normalized, '+'));
            })
            ->first();
    }

    protected function findOrCreateContact(Company $company, string $from): ?Contact
    {
        if ($from === '') {
            return null;
        }

        $contact = Contact::where('company_id', $company->id)
            ->where(function ($q) use ($from) {
                $q->where('phone', $from)
                    ->orWhere('phone', '+'.$from)
                    ->orWhere('phone', ltrim($from, '+'));
            })
            ->first();

        if ($contact) {
            return $contact;
        }

        return Contact::create([
            'company_id' => $company->id,
            'name' => __('Phone caller'),
            'phone' => ltrim($from, '+'),
            'has_chat' => true,
        ]);
    }

    protected function detectHandoff(string $speech, array $phrases): bool
    {
        $lower = strtolower($speech);
        foreach ($phrases as $phrase) {
            if ($phrase !== '' && str_contains($lower, strtolower($phrase))) {
                return true;
            }
        }

        return str_contains($lower, 'human') || str_contains($lower, 'agent') || str_contains($lower, 'person');
    }

    protected function stubFields(?VoicePhoneNumber $line, VoiceCall $voiceCall): array
    {
        $keys = $line?->required_field_keys ?? ['name'];
        $fields = [];
        foreach ($keys as $key) {
            $value = match ($key) {
                'phone' => ltrim($voiceCall->from_number ?? '', '+'),
                'name' => $voiceCall->contact?->name ?? __('Phone caller'),
                default => null,
            };
            $fields[] = [
                'key' => $key,
                'label' => ucfirst($key),
                'value' => $value,
                'status' => $value ? 'confirmed' : 'missing',
            ];
        }

        return $fields;
    }

    protected function extractTelnyxSpeech(array $payload): string
    {
        $speech = $payload['speech'] ?? [];
        if (is_string($speech)) {
            return trim($speech);
        }
        $alternatives = $speech['alternatives'] ?? $speech['speech_alternatives'] ?? [];
        if (is_array($alternatives) && isset($alternatives[0]['transcript'])) {
            return trim((string) $alternatives[0]['transcript']);
        }
        if (isset($speech['transcript'])) {
            return trim((string) $speech['transcript']);
        }

        return trim((string) ($payload['digits'] ?? $payload['result'] ?? ''));
    }
}
