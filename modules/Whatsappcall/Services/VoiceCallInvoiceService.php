<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use App\Models\ListCatalog;
use Illuminate\Support\Facades\Log;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Wpbox\Models\Contact;

class VoiceCallInvoiceService
{
    public function __construct(
        protected VoiceInvoiceSender $invoiceSender,
    ) {
    }

    /**
     * After an AI voice call, create and WhatsApp-send an invoice when the transcript
     * shows a purchase intent and a catalog product can be matched.
     */
    public function maybeSendAfterCall(CallModel $call, Contact $contact, Company $company, ?string $transcript): void
    {
        if (! $this->isEnabled($company)) {
            return;
        }

        if (! $transcript || trim($transcript) === '') {
            Log::info('VoiceCallInvoiceService: skipped — empty transcript', ['call_id' => $call->id]);

            return;
        }

        $existingInvoiceId = data_get($call->structured, 'voice_invoice_id');
        if ($existingInvoiceId) {
            Log::info('VoiceCallInvoiceService: skipped — invoice already sent for call', [
                'call_id' => $call->id,
                'invoice_id' => $existingInvoiceId,
            ]);

            return;
        }

        if (! $this->transcriptIndicatesOrder($transcript)) {
            Log::info('VoiceCallInvoiceService: skipped — no purchase intent in transcript', [
                'call_id' => $call->id,
            ]);

            return;
        }

        $catalogIds = json_decode($company->getConfig('whatsapp_ai_catalog_ids', '[]'), true) ?: [];
        if ($catalogIds === []) {
            Log::warning('VoiceCallInvoiceService: skipped — no catalogs on voice AI settings', [
                'call_id' => $call->id,
                'company_id' => $company->id,
            ]);

            return;
        }

        $match = $this->matchProductFromTranscript($transcript, $company, $catalogIds);
        if (! $match) {
            Log::info('VoiceCallInvoiceService: skipped — could not match product in catalogs', [
                'call_id' => $call->id,
                'transcript_preview' => mb_substr($transcript, 0, 200),
            ]);

            return;
        }

        $quantity = max(1, (int) ($match['quantity'] ?? 1));
        $price = (float) ($match['product']['price'] ?? 0);
        $total = round($price * $quantity, 2);

        if ($total < 1) {
            Log::warning('VoiceCallInvoiceService: skipped — product has no price', [
                'call_id' => $call->id,
                'product' => $match['product']['title'] ?? null,
            ]);

            return;
        }

        $payload = [
            'catalog_id' => $match['catalog_id'],
            'customer_name' => trim((string) ($contact->name ?: 'Customer')),
            'customer_phone' => $this->formatPhone((string) ($call->wa_user_id ?: $contact->phone)),
            'customer_email' => $contact->email ?: null,
            'amount' => $total,
            'description' => 'Order from AI voice call #'.$call->id,
            'items' => [
                [
                    'id' => $match['product']['id'] ?? 'voice-item',
                    'title' => $match['product']['title'] ?? 'Product',
                    'description' => $match['product']['description'] ?? '',
                    'price' => $price,
                    'quantity' => $quantity,
                    'total' => $total,
                ],
            ],
        ];

        $result = $this->invoiceSender->createAndSend($company, $contact, $payload, [
            'call_id' => $call->id,
        ]);

        if (! $result['success']) {
            Log::warning('VoiceCallInvoiceService: invoice failed', [
                'call_id' => $call->id,
                'error' => $result['error'] ?? 'unknown',
            ]);

            return;
        }

        $invoice = $result['invoice'];
        $structured = $call->structured ?? [];
        $structured['voice_invoice_id'] = $invoice->id;
        $structured['voice_invoice_number'] = $invoice->invoice_number;
        $structured['voice_invoice_payment_url'] = $result['payment_url'] ?? null;
        $structured['voice_product_matched'] = $match['product']['title'] ?? null;
        $call->update(['structured' => $structured]);

        Log::info('VoiceCallInvoiceService: invoice sent after voice call', [
            'call_id' => $call->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'whatsapp_sent' => $result['whatsapp_sent'] ?? false,
            'product' => $match['product']['title'] ?? null,
        ]);
    }

    protected function isEnabled(Company $company): bool
    {
        return filter_var(
            $company->getConfig('whatsapp_ai_send_invoice_after_call', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    protected function transcriptIndicatesOrder(string $transcript): bool
    {
        $haystack = strtolower($transcript);
        $needles = [
            'want to buy',
            'i want to buy',
            'like to buy',
            'place an order',
            'order the',
            'order a',
            'order one',
            'purchase',
            'pay for',
            'send me the invoice',
            'send invoice',
            'payment link',
            'i\'ll take',
            'i will take',
        ];

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $catalogIds
     * @return array{catalog_id: int, product: array<string, mixed>, quantity: int}|null
     */
    protected function matchProductFromTranscript(string $transcript, Company $company, array $catalogIds): ?array
    {
        $callerText = $this->extractCallerText($transcript);
        $searchText = strtolower($callerText.' '.$transcript);

        $best = null;
        $bestScore = 0;

        foreach ($catalogIds as $catalogId) {
            $catalog = ListCatalog::where('id', $catalogId)
                ->where('company_id', $company->id)
                ->first();

            if (! $catalog || ! is_array($catalog->items)) {
                continue;
            }

            foreach ($catalog->items as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $titleLower = strtolower($title);
                $score = str_contains($searchText, $titleLower)
                    ? strlen($titleLower) + 100
                    : $this->fuzzyTitleScore($searchText, $titleLower);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'catalog_id' => (int) $catalog->id,
                        'product' => $item,
                        'quantity' => $this->guessQuantity($searchText) ?? 1,
                    ];
                }
            }
        }

        if ($best === null || $bestScore < 15) {
            return null;
        }

        return $best;
    }

    protected function extractCallerText(string $transcript): string
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $transcript) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '[Caller]')) {
                $lines[] = trim(substr($line, 8));
            }
        }

        return implode(' ', $lines);
    }

    protected function fuzzyTitleScore(string $haystack, string $title): int
    {
        $words = array_filter(preg_split('/\s+/', $title) ?: [], fn ($w) => strlen($w) >= 3);
        if ($words === []) {
            return 0;
        }

        $matched = 0;
        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                $matched++;
            }
        }

        if ($matched < max(1, (int) ceil(count($words) * 0.6))) {
            return 0;
        }

        return $matched * 10;
    }

    protected function guessQuantity(string $text): ?int
    {
        if (preg_match('/\b(\d+)\s*(?:x|pieces?|units?|qty)\b/i', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        return null;
    }

    protected function formatPhone(string $phone): string
    {
        $phone = ltrim(trim($phone), '+');

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        if (strlen($phone) === 9 && (str_starts_with($phone, '7') || str_starts_with($phone, '1'))) {
            $phone = '254'.$phone;
        }

        return $phone;
    }
}
