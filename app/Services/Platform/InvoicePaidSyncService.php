<?php

namespace App\Services\Platform;

use App\Services\Outcomes\OutcomeJourneyEnroller;
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
            app(\App\Services\Integrations\PlatformEventBus::class)->emit($company, 'invoice.paid', [
                'invoice_id' => $invoice->id,
                'phone' => $invoice->customer_phone,
                'email' => $invoice->customer_email,
                'amount' => $invoice->amount,
                'customer_name' => $invoice->customer_name,
            ]);
            app(\App\Services\Outcomes\OutcomeSkuBiller::class)->record(
                $company,
                $contact,
                'lead_to_cash',
                'invoice.paid',
                (float) $invoice->amount,
                'invoice',
                (string) $invoice->id,
            );
        }
    }

    private function resolveContact(Invoice $invoice): ?Contact
    {
        $notes = is_array($invoice->notes) ? $invoice->notes : [];
        if (! empty($notes['contact_id'])) {
            return Contact::find($notes['contact_id']);
        }

        return Contact::where('company_id', $invoice->company_id)
            ->where('phone', $invoice->customer_phone)
            ->first();
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

        // Prefer Lead-to-Cash playbook when installed
        if ($company->getConfig('outcome_lead_to_cash_installed', 'no') === 'yes') {
            $moved = app(OutcomeJourneyEnroller::class)->moveToPlaybookStage(
                $company,
                $contact,
                'lead_to_cash',
                'Paid',
                'invoice_paid'
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
