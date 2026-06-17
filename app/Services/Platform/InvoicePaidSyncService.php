<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Log;
use Modules\Contacts\Models\Contact;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;

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

        $existing = $contact->note ?? '';
        $contact->note = trim($existing."\n".$note);
        $contact->save();
    }

    private function moveToPaidStage(Contact $contact, Invoice $invoice): void
    {
        if (! class_exists(Journey::class)) {
            return;
        }

        $journeyId = $invoice->company->getConfig('JOURNEYS_DEFAULT_JOURNEY_ID', '');
        if (! $journeyId) {
            return;
        }

        $paidStage = JourneyStage::where('journey_id', $journeyId)
            ->where(function ($q) {
                $q->where('name', 'like', '%Paid%')
                    ->orWhere('name', 'like', '%Won%');
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
