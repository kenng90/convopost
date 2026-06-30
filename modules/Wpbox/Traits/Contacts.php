<?php

namespace Modules\Wpbox\Traits;

use App\Models\Company;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Support\PhoneNormalizer;

trait Contacts
{
    public function findContactByPhone(Company $company, string $phone): ?Contact
    {
        $normalizer = app(PhoneNormalizer::class);
        $dialCode = $normalizer->dialCodeForCompany($company);
        $candidates = $normalizer->lookupCandidates($phone, $dialCode);

        if ($candidates === []) {
            return null;
        }

        return Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('phone', $candidates)
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
        $normalizedPhone = $this->normalizeContactPhone($phone, $company);
        $contact = $this->findContactByPhone($company, $normalizedPhone);

        if (! $contact) {
            $contact = Contact::create([
                'name' => $name,
                'phone' => $normalizedPhone,
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
        $normalizedPhone = $this->normalizeContactPhone($phone, $company);
        $contact = $this->findContactByPhone($company, $normalizedPhone);

        if ($contact) {
            if ($contact->phone !== $normalizedPhone) {
                $contact->phone = $normalizedPhone;
                $contact->save();
            }

            return $contact;
        }

        $showInInbox = $this->bookingContactsAppearInInbox($company);

        return Contact::create([
            'name' => $name,
            'phone' => $normalizedPhone,
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

    protected function normalizeContactPhone(string $phone, Company $company): string
    {
        $normalizer = app(PhoneNormalizer::class);

        return $normalizer->normalize($phone, $normalizer->dialCodeForCompany($company));
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

    protected function redirectToContactChat(Contact $contact): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('chat.index', ['contact' => $contact->id]);
    }

    protected function findBookingContact(?int $contactId, int $companyId): Contact
    {
        if (! $contactId) {
            abort(404);
        }

        $contact = Contact::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->find($contactId);

        if (! $contact) {
            abort(404);
        }

        return $contact;
    }
}
