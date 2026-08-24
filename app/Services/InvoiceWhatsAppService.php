<?php

namespace App\Services;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\WhatsApp\OrderInvoiceMessageTemplateService;
use App\Services\WhatsApp\WhatsAppGraphClient;
use App\Services\WhatsApp\WhatsAppSessionWindow;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Template;

class InvoiceWhatsAppService
{
    public function __construct(
        protected Company $company,
        protected ?WhatsAppSessionWindow $sessionWindow = null,
        protected ?OrderInvoiceMessageTemplateService $templateService = null,
    ) {
        $this->sessionWindow ??= new WhatsAppSessionWindow;
        $this->templateService ??= new OrderInvoiceMessageTemplateService;
    }

    /**
     * Send invoice via WhatsApp (session text inside 24h window, template outside).
     */
    public function sendInvoice(Invoice $invoice, ?Contact $contact = null): bool
    {
        try {
            $graph = new WhatsAppGraphClient($this->company);

            if (! $graph->hasMessagingCredentials()) {
                Log::warning('WhatsApp credentials not configured for invoice sending', [
                    'company_id' => $this->company->id,
                    'invoice_id' => $invoice->id,
                ]);

                return false;
            }

            $phoneNumber = $this->formatPhoneNumber($invoice->customer_phone);
            $contact ??= $this->resolveContact($invoice, $phoneNumber);

            if ($this->sessionWindow->isOpen($contact)) {
                return $this->sendSessionText($graph, $invoice, $phoneNumber);
            }

            return $this->sendOutsideSessionTemplate($graph, $invoice, $phoneNumber);
        } catch (\Exception $e) {
            Log::error('Exception sending invoice via WhatsApp', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function sendSessionText(WhatsAppGraphClient $graph, Invoice $invoice, string $phoneNumber): bool
    {
        $message = $this->buildInvoiceMessage($invoice);
        $response = $graph->sendTextMessage($phoneNumber, $message);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            Log::error('Failed to send invoice session text via WhatsApp', [
                'invoice_id' => $invoice->id,
                'status' => $response['status'],
                'response' => $response['content'],
            ]);

            return false;
        }

        Log::info('Invoice sent via WhatsApp session message', [
            'invoice_id' => $invoice->id,
            'message_id' => is_array($response['content']) ? ($response['content']['messages'][0]['id'] ?? null) : null,
        ]);

        return true;
    }

    protected function sendOutsideSessionTemplate(WhatsAppGraphClient $graph, Invoice $invoice, string $phoneNumber): bool
    {
        $ensure = $this->templateService->ensureForCompany($this->company);

        if (! $ensure['ready']) {
            Log::warning('Invoice template not ready for outside-session send', [
                'invoice_id' => $invoice->id,
                'company_id' => $this->company->id,
                'template_status' => $ensure['status'],
                'message' => $ensure['message'],
            ]);

            return false;
        }

        $template = $ensure['template'] ?? $this->findApprovedTemplate();
        if (! $template) {
            Log::warning('Approved invoice template missing after ensure', [
                'invoice_id' => $invoice->id,
                'company_id' => $this->company->id,
            ]);

            return false;
        }

        $components = $this->buildTemplateComponents($invoice);
        $response = $graph->sendTemplateMessage(
            $phoneNumber,
            $template->name,
            $template->language,
            $components
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            Log::error('Failed to send invoice template via WhatsApp', [
                'invoice_id' => $invoice->id,
                'status' => $response['status'],
                'response' => $response['content'],
            ]);

            return false;
        }

        Log::info('Invoice sent via WhatsApp template', [
            'invoice_id' => $invoice->id,
            'template' => $template->name,
            'message_id' => is_array($response['content']) ? ($response['content']['messages'][0]['id'] ?? null) : null,
        ]);

        return true;
    }

    protected function findApprovedTemplate(): ?Template
    {
        $name = $this->templateService->getTemplateName($this->company);
        $language = $this->templateService->getTemplateLanguage($this->company);

        return Template::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->company->id)
            ->where('name', $name)
            ->where('language', $language)
            ->where('status', 'APPROVED')
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildTemplateComponents(Invoice $invoice): array
    {
        return [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $invoice->customer_name ?: 'Valued Customer'],
                    ['type' => 'text', 'text' => (string) $invoice->invoice_number],
                    ['type' => 'text', 'text' => $this->buildItemsSummary($invoice)],
                    ['type' => 'text', 'text' => 'KES '.number_format((float) $invoice->amount, 2)],
                    ['type' => 'text', 'text' => $this->buildPaymentLink($invoice)],
                ],
            ],
        ];
    }

    protected function buildItemsSummary(Invoice $invoice): string
    {
        if (! $invoice->items || ! is_array($invoice->items)) {
            return 'See invoice link for details.';
        }

        $lines = [];
        foreach (array_slice($invoice->items, 0, 3) as $item) {
            $itemTitle = $item['title'] ?? 'Item';
            $qty = $item['quantity'] ?? 1;
            $itemTotal = $item['total'] ?? (($item['price'] ?? 0) * $qty);
            $lines[] = '• '.$itemTitle.' (x'.$qty.') - KES '.number_format((float) $itemTotal, 2);
        }

        $remaining = count($invoice->items) - 3;
        if ($remaining > 0) {
            $lines[] = '• +'.$remaining.' more item(s)';
        }

        return implode("\n", $lines) ?: 'See invoice link for details.';
    }

    protected function buildPaymentLink(Invoice $invoice): string
    {
        $invoiceIdentifier = $invoice->public_uuid ?? $invoice->id;

        return rtrim(config('app.url'), '/').'/catalog/pay/'.$invoiceIdentifier;
    }

    protected function resolveContact(Invoice $invoice, string $phoneNumber): ?Contact
    {
        return Contact::query()
            ->where('company_id', $invoice->company_id)
            ->where(function ($query) use ($phoneNumber) {
                $query->where('phone', $phoneNumber)
                    ->orWhere('phone', '+'.$phoneNumber);
            })
            ->first();
    }

    /**
     * Build invoice message for session (24h window) sends.
     */
    public function buildInvoiceMessage(Invoice $invoice): string
    {
        $paymentLink = $this->buildPaymentLink($invoice);

        $itemsText = '';
        if ($invoice->items && is_array($invoice->items)) {
            foreach ($invoice->items as $item) {
                $itemTitle = $item['title'] ?? 'Item';
                $qty = $item['quantity'] ?? 1;
                $itemTotal = $item['total'] ?? ($item['price'] * $qty);
                $itemsText .= "• {$itemTitle} (x{$qty}) - KES ".number_format($itemTotal, 2)."\n";
            }
        }

        $message = '📋 *Invoice #'.$invoice->invoice_number."*\n\n";
        $message .= 'Hello '.($invoice->customer_name ?? 'Valued Customer').",\n\n";
        $message .= 'Thank you for your order from *'.$this->company->name."*\n\n";

        if ($itemsText) {
            $message .= "*Items:*\n";
            $message .= $itemsText."\n";
        }

        $message .= '*Total Amount:* KES '.number_format($invoice->amount, 2)."\n";
        $message .= '*Status:* '.ucfirst($invoice->status)."\n\n";
        $message .= "💳 *Click the link below to view and pay your invoice:*\n";
        $message .= $paymentLink."\n\n";
        $message .= "For any questions or concerns, please don't hesitate to contact us.\n\n";
        $message .= 'Thank you for your business! 🙏';

        return $message;
    }

    /**
     * Format phone number for WhatsApp (254XXXXXXXXX format)
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        if (strlen($phone) === 9 && (str_starts_with($phone, '7') || str_starts_with($phone, '1'))) {
            $phone = '254'.$phone;
        }

        return $phone;
    }

    public function sendChaseMessage(Invoice $invoice, string $message): bool
    {
        try {
            $graph = new WhatsAppGraphClient($this->company);
            if (! $graph->hasMessagingCredentials()) {
                return false;
            }

            $phoneNumber = $this->formatPhoneNumber($invoice->customer_phone);
            $contact = $this->resolveContact($invoice, $phoneNumber);

            if ($contact && $this->sessionWindow->isOpen($contact)) {
                $response = $graph->sendTextMessage($phoneNumber, $message);

                return $response['status'] >= 200 && $response['status'] < 300;
            }

            return $this->sendInvoice($invoice, $contact);
        } catch (\Throwable $e) {
            Log::info('Collection chase message failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
