<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WebhookCompanyResolver
{
    public const COMPANY_MODEL = 'App\Models\Company';

    public const KEY_PHONE_NUMBER_ID = 'whatsapp_phone_number_id';

    public const KEY_BUSINESS_ACCOUNT_ID = 'whatsapp_business_account_id';

    public function resolveFromWebhookRequest(Request $request): ?Company
    {
        $entry = $request->input('entry.0', []);
        if (! is_array($entry)) {
            return null;
        }

        $value = $entry['changes'][0]['value'] ?? [];
        $phoneNumberId = isset($value['metadata']['phone_number_id'])
            ? (string) $value['metadata']['phone_number_id']
            : null;
        $wabaId = isset($entry['id']) ? (string) $entry['id'] : null;

        if ($phoneNumberId !== null && $phoneNumberId !== '') {
            $company = $this->resolveByConfigKey(self::KEY_PHONE_NUMBER_ID, $phoneNumberId);
            if ($company !== null) {
                return $company;
            }
        }

        if ($wabaId !== null && $wabaId !== '') {
            return $this->resolveByConfigKey(self::KEY_BUSINESS_ACCOUNT_ID, $wabaId);
        }

        return null;
    }

    public function resolveByConfigKey(string $key, string $value): ?Company
    {
        $matches = $this->findCompanyConfigMatches($key, $value);

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() > 1) {
            Log::warning('WhatsApp webhook: ambiguous organisation config match', [
                'key' => $key,
                'value' => $value,
                'company_ids' => $matches->pluck('model_id')->unique()->values()->all(),
            ]);
        }

        $company = Company::find($matches->first()->model_id);

        return $company instanceof Company ? $company : null;
    }

    public function phoneNumberIdUsedByAnotherCompany(string $phoneNumberId, int $companyId): bool
    {
        if ($phoneNumberId === '') {
            return false;
        }

        return Config::query()
            ->where('key', self::KEY_PHONE_NUMBER_ID)
            ->where('value', $phoneNumberId)
            ->where('model_type', self::COMPANY_MODEL)
            ->where('model_id', '!=', $companyId)
            ->exists();
    }

    /**
     * @return Collection<int, Config>
     */
    protected function findCompanyConfigMatches(string $key, string $value): Collection
    {
        return Config::query()
            ->where('key', $key)
            ->where('value', $value)
            ->where('model_type', self::COMPANY_MODEL)
            ->orderBy('model_id')
            ->get();
    }
}
