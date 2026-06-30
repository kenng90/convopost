<?php

namespace App\Services\Catalog;

use App\Models\Company;

class CatalogWhatsAppOrderService
{
    public const CONFIG_KEY = 'whatsapp_phone_number';

    public function resolveNumber(Company $company): ?string
    {
        $raw = $company->getConfig(self::CONFIG_KEY, '');
        if ($raw === '' || $raw === null) {
            $raw = $company->whatsapp_phone ?? '';
        }

        return $this->normalizeForWaMe($raw);
    }

    public function displayValue(Company $company): string
    {
        $configured = $company->getConfig(self::CONFIG_KEY, '');
        if ($configured !== '' && $configured !== null) {
            return (string) $configured;
        }

        return (string) ($company->whatsapp_phone ?? '');
    }

    public function save(Company $company, ?string $number): void
    {
        $company->setConfig(self::CONFIG_KEY, trim((string) $number));
    }

    public function normalizeForWaMe(?string $number): ?string
    {
        if ($number === null || $number === '' || $number === '000000000') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number);

        return $digits !== '' ? $digits : null;
    }
}
