<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;

class InvoiceWhatsAppService
{
    protected $company;
    protected $facebookAPI = 'https://graph.facebook.com/v19.0/';

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Send invoice via WhatsApp
     */
    public function sendInvoice(Invoice $invoice): bool
    {
        try {
            // Get WhatsApp credentials from company config (same as Wpbox trait)
            $phoneNumberId = $this->getPhoneID();
            $accessToken = $this->getToken();

            if (!$phoneNumberId || !$accessToken) {
                Log::warning('WhatsApp credentials not configured for invoice sending', [
                    'company_id' => $this->company->id,
                    'invoice_id' => $invoice->id,
                    'phone_id' => $phoneNumberId ? 'exists' : 'missing',
                    'token' => $accessToken ? 'exists' : 'missing',
                ]);
                return false;
            }

            // Format customer phone number
            $phoneNumber = $this->formatPhoneNumber($invoice->customer_phone);

            // Build invoice message
            $message = $this->buildInvoiceMessage($invoice);

            // Send WhatsApp message (same format as Wpbox trait)
            $url = $this->facebookAPI . $phoneNumberId . '/messages';

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                    'preview_url' => true,
                ],
            ];

            Log::info('Sending invoice via WhatsApp', [
                'invoice_id' => $invoice->id,
                'phone' => $phoneNumber,
                'company_id' => $this->company->id,
                'url' => $url,
            ]);

            // Use proper authorization header like Wpbox does
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Failed to send invoice via WhatsApp', [
                    'invoice_id' => $invoice->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }

            Log::info('Invoice sent via WhatsApp successfully', [
                'invoice_id' => $invoice->id,
                'message_id' => $response->json('messages.0.id'),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception sending invoice via WhatsApp', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get access token (same as Wpbox trait)
     */
    private function getToken(): string
    {
        return $this->company->getConfig('whatsapp_permanent_access_token', '');
    }

    /**
     * Get phone number ID (same as Wpbox trait)
     */
    private function getPhoneID(): string
    {
        return $this->company->getConfig('whatsapp_phone_number_id', '');
    }

    /**
     * Build invoice message
     */
    private function buildInvoiceMessage(Invoice $invoice): string
    {
        // Use UUID if available, fallback to ID for backward compatibility
        $invoiceIdentifier = $invoice->public_uuid ?? $invoice->id;
        $paymentLink = config('app.url') . '/catalog/pay/' . $invoiceIdentifier;

        // Calculate items summary
        $itemsText = '';
        if ($invoice->items && is_array($invoice->items)) {
            foreach ($invoice->items as $item) {
                $itemTitle = $item['title'] ?? 'Item';
                $qty = $item['quantity'] ?? 1;
                $itemTotal = $item['total'] ?? ($item['price'] * $qty);
                $itemsText .= "• {$itemTitle} (x{$qty}) - KES " . number_format($itemTotal, 2) . "\n";
            }
        }

        $message = "📋 *Invoice #" . $invoice->invoice_number . "*\n\n";
        $message .= "Hello " . ($invoice->customer_name ?? 'Valued Customer') . ",\n\n";
        $message .= "Thank you for your order from *" . $this->company->name . "*\n\n";

        if ($itemsText) {
            $message .= "*Items:*\n";
            $message .= $itemsText . "\n";
        }

        $message .= "*Total Amount:* KES " . number_format($invoice->amount, 2) . "\n";
        $message .= "*Status:* " . ucfirst($invoice->status) . "\n\n";

        $message .= "💳 *Click the link below to view and pay your invoice:*\n";
        $message .= $paymentLink . "\n\n";

        $message .= "For any questions or concerns, please don't hesitate to contact us.\n\n";
        $message .= "Thank you for your business! 🙏";

        return $message;
    }

    /**
     * Format phone number for WhatsApp (254XXXXXXXXX format)
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any + prefix
        $phone = ltrim($phone, '+');

        // If starts with 0, replace with 254
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }

        // If starts with 7 or 1 (local format without country code)
        if (strlen($phone) === 9 && (str_starts_with($phone, '7') || str_starts_with($phone, '1'))) {
            $phone = '254' . $phone;
        }

        return $phone;
    }
}
