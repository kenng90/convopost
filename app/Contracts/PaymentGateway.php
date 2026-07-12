<?php

namespace App\Contracts;

use App\Models\Company;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(Company $company): bool;

    /**
     * @return list<string>
     */
    public function configErrors(Company $company): array;

    /**
     * @return array{
     *     success: bool,
     *     payment?: InvoicePayment,
     *     authorization_url?: string|null,
     *     reference?: string|null,
     *     message?: string
     * }
     */
    public function initiate(Company $company, Invoice $invoice, array $options = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, payment?: InvoicePayment|null, message?: string}
     */
    public function handleWebhook(array $payload): array;
}
