<?php

namespace Modules\Wpbox\Traits;

use App\Models\Company;
use Modules\Wpbox\Models\Contact;

trait Contacts
{
    public function findContactByPhone(Company $company, string $phone): ?Contact
    {
        $normalized = ltrim($phone, '+');

        return Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($phone, $normalized) {
                $query->where('phone', $phone)
                    ->orWhere('phone', '+'.$normalized)
                    ->orWhere('phone', $normalized);
            })
            ->first();
    }

    protected function setWebhookCompanyContext(Company $company): void
    {
        session([
            'company_id' => $company->id,
            'company_currency' => $company->currency,
            'company_convertion' => $company->do_covertion,
        ]);
    }

    public function getOrMakeContact($phone, $company, $name)
    {
        //Find the contact
        $contact = $this->findContactByPhone($company, $phone);

        if (! $contact) {
            //Create new contact
            $contact = Contact::create([
                'name' => $name,
                'phone' => $phone,
                'avatar' => '',
                'company_id' => $company->id,
                'has_chat' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'last_support_reply_at' => null,
                'last_reply_at' => now(),
                'last_message' => '',
                'is_last_message_by_contact' => true,
            ]);
        }

        return $contact;
    }

    public function bookingContactsAppearInInbox(Company $company): bool
    {
        return filter_var($company->getConfig('BOOKING_CONTACTS_IN_INBOX', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public function getOrMakeBookingContact($phone, Company $company, $name): Contact
    {
        $contact = $this->findContactByPhone($company, $phone);

        if ($contact) {
            return $contact;
        }

        $showInInbox = $this->bookingContactsAppearInInbox($company);

        return Contact::create([
            'name' => $name,
            'phone' => $phone,
            'avatar' => '',
            'company_id' => $company->id,
            'has_chat' => $showInInbox,
            'created_at' => now(),
            'updated_at' => now(),
            'last_support_reply_at' => null,
            'last_reply_at' => $showInInbox ? now() : null,
            'last_message' => '',
            'is_last_message_by_contact' => $showInInbox,
        ]);
    }

    public function promoteContactToInbox(Contact $contact): Contact
    {
        $contact->has_chat = true;
        $contact->resolved_chat = false;

        if (! $contact->last_reply_at) {
            $contact->last_reply_at = now();
        }

        if ($contact->last_message === '') {
            $contact->last_message = __('Opened from booking');
        }

        $contact->save();

        return $contact;
    }
}
