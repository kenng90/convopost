<?php

namespace App\Services\Platform;

use App\Services\Outcomes\OutcomeJourneyEnroller;
use App\Services\Outcomes\SocialOutcomeHookService;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;
use Modules\Wpbox\Models\Contact;

class InvoicePaidSyncService
{
    public function sync(Invoice $invoice): void
    {
        if ($invoice->status !== 'paid') {
            return;
        }

        $contact = $this->resolveContact($invoice);
        if (! $contact) {
            return;
        }

        $this->addPaymentNote($contact, $invoice);
        $this->moveToPaidStage($contact, $invoice);

        $company = \App\Models\Company::find($invoice->company_id);
        if ($company) {
            $socialHook = app(SocialOutcomeHookService::class);
            $socialMeta = $socialHook->attributionMetadata($invoice);

            if ($socialMeta !== []) {
                $socialHook->onSocialInvoicePaid($company, $invoice, $contact);
            }

            app(\App\Services\Integrations\PlatformEventBus::class)->emit($company, 'invoice.paid', array_filter([
                'invoice_id' => $invoice->id,
                'phone' => $invoice->customer_phone,
                'email' => $invoice->customer_email,
                'amount' => $invoice->amount,
                'customer_name' => $invoice->customer_name,
                'social_post_id' => $invoice->social_post_id,
                'social_offer_link_id' => $invoice->social_offer_link_id,
            ], fn ($value) => $value !== null && $value !== ''));

            app(\App\Services\Outcomes\OutcomeSkuBiller::class)->record(
                $company,
                $contact,
                'lead_to_cash',
                'invoice.paid',
                (float) $invoice->amount,
                'invoice',
                (string) $invoice->id,
                $socialMeta,
            );
        }
    }

    private function resolveContact(Invoice $invoice): ?Contact
    {
        $notes = is_array($invoice->notes) ? $invoice->notes : [];
        if (! empty($notes['contact_id'])) {
            return Contact::find($notes['contact_id']);
        }

        $phone = trim((string) ($invoice->customer_phone ?? ''));

        if ($phone === '') {
            return null;
        }

        $existing = Contact::where('company_id', $invoice->company_id)
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Social-attributed checkouts may not have a CRM contact yet — create without messaging.
        if ($invoice->social_post_id) {
            return Contact::create([
                'company_id' => $invoice->company_id,
                'phone' => $phone,
                'name' => $invoice->customer_name ?: $phone,
                'email' => $invoice->customer_email,
                'subscribed' => 1,
            ]);
        }

        return null;
    }

    private function addPaymentNote(Contact $contact, Invoice $invoice): void
    {
        $note = __('Invoice :number paid — :amount :currency', [
            'number' => $invoice->invoice_number,
            'amount' => number_format((float) $invoice->amount, 2),
            'currency' => $invoice->currency,
        ]);

        $contact->addNote($note);
    }

    private function moveToPaidStage(Contact $contact, Invoice $invoice): void
    {
        if (! class_exists(Journey::class)) {
            return;
        }

        $company = $invoice->company;
        if (! $company) {
            return;
        }

        // Prefer Lead-to-Cash playbook when installed (social-attributed or otherwise).
        if ($company->getConfig('outcome_lead_to_cash_installed', 'no') === 'yes') {
            $moved = app(OutcomeJourneyEnroller::class)->moveToPlaybookStage(
                $company,
                $contact,
                'lead_to_cash',
                'Paid',
                $invoice->social_post_id ? 'social_order_paid' : 'invoice_paid'
            );

            if ($moved) {
                return;
            }
        }

        $journeyId = $company->getConfig('outcome_lead_to_cash_journey_id', '')
            ?: $company->getConfig('JOURNEYS_DEFAULT_JOURNEY_ID', '');

        if (! $journeyId) {
            return;
        }

        $paidStage = JourneyStage::where('journey_id', $journeyId)
            ->where(function ($q) {
                $q->where('name', 'like', '%Paid%')
                    ->orWhere('name', 'like', '%Won%')
                    ->orWhere('name', 'like', '%Recovered%');
            })
            ->orderBy('order')
            ->first();

        if (! $paidStage) {
            return;
        }

        try {
            app(JourneyContactService::class)->moveContactToStage($contact, $paidStage, 'invoice_paid');
        } catch (\Throwable $e) {
            Log::warning('Invoice paid journey sync failed', [
                'invoice_id' => $invoice->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
