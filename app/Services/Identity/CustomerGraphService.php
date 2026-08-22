<?php

namespace App\Services\Identity;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelIdentity;
use App\Services\Contacts\ContactMergeService;
use App\Services\Platform\Customer360Service;
use Modules\Wpbox\Models\Contact;

class CustomerGraphService
{
    /**
     * Find an existing contact by phone, email, or channel identity, or create one.
     *
     * @param  array{name?: string, phone?: string|null, email?: string|null, channel?: string|null, external_id?: string|null}  $identity
     */
    public function findOrCreate(Company $company, array $identity): Contact
    {
        $phone = $this->normalizePhone($identity['phone'] ?? null);
        $email = $this->normalizeEmail($identity['email'] ?? null);
        $channel = $identity['channel'] ?? null;
        $externalId = $identity['external_id'] ?? null;

        $contact = $this->find($company, $phone, $email, $channel, $externalId);

        if (! $contact) {
            $contact = Contact::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => $identity['name'] ?? ($phone ?: $email ?: 'Customer'),
                'phone' => $phone ?: '',
                'email' => $email,
                'subscribed' => 1,
                'has_chat' => true,
                'enabled_ai_bot' => true,
            ]);
        }

        if ($channel && $externalId) {
            $this->attachIdentity($company, $contact, $channel, $externalId, $identity['name'] ?? null);
        }

        return $contact->fresh();
    }

    public function find(Company $company, ?string $phone, ?string $email, ?string $channel = null, ?string $externalId = null): ?Contact
    {
        if ($channel && $externalId) {
            $identity = ChannelIdentity::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('channel', $channel)
                ->where('external_id', $externalId)
                ->first();
            if ($identity) {
                return Contact::withoutGlobalScopes()->find($identity->contact_id);
            }
        }

        if ($phone) {
            $byPhone = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                        ->orWhere('phone', '+'.$phone)
                        ->orWhere('phone', ltrim($phone, '+'));
                })
                ->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        if ($email) {
            return Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->first();
        }

        return null;
    }

    public function mergeIfDuplicate(Company $company, Contact $contact): Contact
    {
        $other = null;
        if ($contact->phone) {
            $other = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', '!=', $contact->id)
                ->where('phone', $contact->phone)
                ->first();
        }
        if (! $other && $contact->email) {
            $other = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', '!=', $contact->id)
                ->where('email', $contact->email)
                ->first();
        }

        if (! $other) {
            return $contact;
        }

        return app(ContactMergeService::class)->merge($contact, $other);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Company $company, Contact $contact): array
    {
        $payload = app(Customer360Service::class)->forContact($company, $contact);
        $payload['identities'] = ChannelIdentity::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->get()
            ->map(fn (ChannelIdentity $identity) => [
                'channel' => $identity->channel instanceof MessagingChannelType
                    ? $identity->channel->value
                    : (string) $identity->channel,
                'external_id' => $identity->external_id,
                'display_name' => $identity->display_name,
            ])
            ->all();

        return $payload;
    }

    private function attachIdentity(Company $company, Contact $contact, string $channel, string $externalId, ?string $displayName): void
    {
        ChannelIdentity::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'channel' => $channel,
                'external_id' => $externalId,
            ],
            [
                'contact_id' => $contact->id,
                'display_name' => $displayName,
            ]
        );
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\s+/', '', (string) $phone);

        return $phone !== '' ? $phone : null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }
}
