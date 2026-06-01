<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use App\Services\InvoiceWhatsAppService;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Wpbox\Models\Contact;

class VoiceInvoiceSender
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, error?: string, invoice?: Invoice, whatsapp_sent?: bool, payment_url?: string}
     */
    public function createAndSend(Company $company, Contact $contact, array $payload, array $context = []): array
    {
        try {
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'catalog_id' => $payload['catalog_id'],
                'invoice_number' => Invoice::generateInvoiceNumber($company),
                'customer_name' => $payload['customer_name'],
                'customer_phone' => $payload['customer_phone'],
                'customer_email' => $payload['customer_email'],
                'amount' => $payload['amount'],
                'currency' => 'KES',
                'status' => 'draft',
                'description' => $payload['description'],
                'items' => $payload['items'],
            ]);

            $whatsAppService = new InvoiceWhatsAppService($company);
            $whatsAppSent = $whatsAppService->sendInvoice($invoice);

            if ($whatsAppSent) {
                $invoice->markAsSent();
            }

            $invoice->refresh();
            $identifier = $invoice->public_uuid ?? $invoice->id;
            $paymentUrl = rtrim(config('app.url'), '/').'/catalog/pay/'.$identifier;

            Log::info('VoiceInvoiceSender: invoice created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'call_id' => $context['call_id'] ?? null,
                'whatsapp_sent' => $whatsAppSent,
            ]);

            return [
                'success' => true,
                'invoice' => $invoice,
                'whatsapp_sent' => $whatsAppSent,
                'payment_url' => $paymentUrl,
            ];
        } catch (\Throwable $th) {
            Log::error('VoiceInvoiceSender: failed to create invoice', [
                'error' => $th->getMessage(),
                'company_id' => $company->id,
                'contact_id' => $contact->id,
            ]);

            return [
                'success' => false,
                'error' => $th->getMessage(),
            ];
        }
    }
}
