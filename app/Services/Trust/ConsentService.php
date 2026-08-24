<?php

namespace App\Services\Trust;

use App\Models\Company;
use App\Models\ConsentRecord;
use Modules\Wpbox\Models\Contact;

class ConsentService
{
    public function record(
        Company $company,
        ?Contact $contact,
        string $type = 'opt_in',
        string $channel = 'whatsapp',
        string $source = 'inbound',
    ): ConsentRecord {
        return ConsentRecord::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact?->id,
            'channel' => $channel,
            'type' => $type,
            'source' => $source,
            'recorded_at' => now(),
        ]);
    }

    public function hasOptIn(Company $company, Contact $contact, string $channel = 'whatsapp'): bool
    {
        return ConsentRecord::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->where('channel', $channel)
            ->where('type', 'opt_in')
            ->exists();
    }

    public function hasOptOut(Company $company, Contact $contact, string $channel = 'whatsapp'): bool
    {
        $latest = ConsentRecord::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->where('channel', $channel)
            ->orderByDesc('id')
            ->first();

        return $latest?->type === 'opt_out';
    }

    /**
     * @return array{opt_ins: int, opt_outs: int, missing_opt_in_contacts: int}
     */
    public function summary(Company $company): array
    {
        $base = ConsentRecord::withoutGlobalScopes()->where('company_id', $company->id);

        return [
            'opt_ins' => (clone $base)->where('type', 'opt_in')->count(),
            'opt_outs' => (clone $base)->where('type', 'opt_out')->count(),
            'missing_opt_in_contacts' => Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('subscribed', 1)
                ->whereDoesntHave('messages')
                ->count(),
        ];
    }
}
