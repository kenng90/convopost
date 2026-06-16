<?php

namespace App\Services\Catalog;

use App\Models\Company;

class CatalogCurrencyService
{
    public function codeForCompany(?Company $company): string
    {
        $currency = strtoupper(trim((string) ($company?->currency ?? '')));

        return $currency !== '' ? $currency : 'KES';
    }

    public function symbolForCode(string $code): string
    {
        return match (strtoupper($code)) {
            'KES' => 'KSh',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $code.' ',
        };
    }

    public function formatAmount(?Company $company, float $amount): string
    {
        $code = $this->codeForCompany($company);
        $symbol = $this->symbolForCode($code);
        $formatted = number_format($amount, 2, '.', ',');

        if (in_array($code, ['USD', 'EUR', 'GBP'], true)) {
            return $symbol.$formatted;
        }

        return trim($symbol).' '.$formatted;
    }
}
