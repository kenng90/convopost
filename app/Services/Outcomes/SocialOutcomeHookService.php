<?php

namespace App\Services\Outcomes;

use App\Models\Company;
use App\Services\Integrations\PlatformEventBus;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Wpbox\Models\Contact;

/**
 * Connect social-attributed orders to Commerce Ops playbooks without WhatsApp sends.
 */
class SocialOutcomeHookService
{
    public function __construct(
        private readonly PlatformEventBus $events,
    ) {
    }

    /**
     * @return array{handled: bool, contact_id: ?int, event_emitted: bool}
     */
    public function onSocialInvoicePaid(Company $company, Invoice $invoice, ?Contact $contact = null): array
    {
        if ($invoice->status !== 'paid' || ! $invoice->social_post_id) {
            return ['handled' => false, 'contact_id' => null, 'event_emitted' => false];
        }

        $contact ??= $this->resolveOrCreateContact($company, $invoice);

        $this->events->emit($company, 'social.order.paid', array_filter([
            'invoice_id' => $invoice->id,
            'contact_id' => $contact?->id,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'social_post_id' => (int) $invoice->social_post_id,
            'social_offer_link_id' => $invoice->social_offer_link_id
                ? (int) $invoice->social_offer_link_id
                : null,
            'phone' => $invoice->customer_phone,
        ], fn ($value) => $value !== null && $value !== ''));

        Log::info('Social outcome hook processed paid invoice', [
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'social_post_id' => $invoice->social_post_id,
            'contact_id' => $contact?->id,
        ]);

        return [
            'handled' => true,
            'contact_id' => $contact?->id,
            'event_emitted' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributionMetadata(Invoice $invoice): array
    {
        if (! $invoice->social_post_id) {
            return [];
        }

        return array_filter([
            'source_channel' => 'social',
            'social_post_id' => (int) $invoice->social_post_id,
            'social_offer_link_id' => $invoice->social_offer_link_id
                ? (int) $invoice->social_offer_link_id
                : null,
        ], fn ($value) => $value !== null);
    }

    protected function resolveOrCreateContact(Company $company, Invoice $invoice): ?Contact
    {
        $notes = is_array($invoice->notes) ? $invoice->notes : [];

        if (! empty($notes['contact_id'])) {
            $existing = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->find($notes['contact_id']);

            if ($existing) {
                return $existing;
            }
        }

        $phone = trim((string) ($invoice->customer_phone ?? ''));

        if ($phone === '') {
            return null;
        }

        return Contact::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $company->id,
                'phone' => $phone,
            ],
            [
                'name' => $invoice->customer_name ?: $phone,
                'email' => $invoice->customer_email,
                'subscribed' => 1,
            ]
        );
    }
}
