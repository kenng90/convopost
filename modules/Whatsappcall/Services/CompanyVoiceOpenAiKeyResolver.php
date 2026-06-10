<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;

class CompanyVoiceOpenAiKeyResolver
{
    /**
     * OpenAI API key for voice Realtime — company setting only (no platform .env fallback).
     */
    public function resolve(Company $company): ?string
    {
        $companyKey = trim((string) $company->getConfig('whatsapp_ai_openai_api_key', ''));

        return $companyKey !== '' ? $companyKey : null;
    }

    public function isConfigured(Company $company): bool
    {
        return $this->resolve($company) !== null;
    }
}
