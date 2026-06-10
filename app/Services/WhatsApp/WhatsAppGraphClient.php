<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppGraphClient
{
    protected string $facebookApi = 'https://graph.facebook.com/v19.0/';

    public function __construct(protected Company $company)
    {
    }

    public function hasMessagingCredentials(): bool
    {
        return strlen($this->getToken()) > 5 && strlen($this->getPhoneId()) > 0;
    }

    public function hasTemplateCredentials(): bool
    {
        return strlen($this->getToken()) > 5 && strlen($this->getAccountId()) > 0;
    }

    public function getToken(): string
    {
        return (string) $this->company->getConfig('whatsapp_permanent_access_token', '');
    }

    public function getPhoneId(): string
    {
        return (string) $this->company->getConfig('whatsapp_phone_number_id', '');
    }

    public function getAccountId(): string
    {
        return (string) $this->company->getConfig('whatsapp_business_account_id', '');
    }

    /**
     * @return array{status: int, content: array<string, mixed>|string}
     */
    public function submitMessageTemplate(array $templateData): array
    {
        $url = $this->facebookApi.$this->getAccountId().'/message_templates';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getToken(),
                'Content-Type' => 'application/json',
            ])->post($url, $templateData);

            return [
                'status' => $response->status(),
                'content' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsAppGraphClient: template submit failed', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'content' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessageTemplates(?string $name = null): array
    {
        if (! $this->hasTemplateCredentials()) {
            return [];
        }

        $url = $this->facebookApi.$this->getAccountId().'/message_templates';
        $query = [
            'fields' => 'name,category,language,status,components,id',
            'limit' => 100,
        ];

        if ($name !== null) {
            $query['name'] = $name;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getToken(),
            ])->get($url, $query);

            if (! $response->successful()) {
                return [];
            }

            return $response->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppGraphClient: list templates failed', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array{status: int, content: array<string, mixed>|string}
     */
    public function sendTemplateMessage(string $to, string $templateName, string $language, array $components): array
    {
        $url = $this->facebookApi.$this->getPhoneId().'/messages';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getToken(),
                'Content-Type' => 'application/json',
            ])->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $language],
                    'components' => $components,
                ],
            ]);

            return [
                'status' => $response->status(),
                'content' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsAppGraphClient: send template failed', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'content' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: int, content: array<string, mixed>|string}
     */
    public function sendTextMessage(string $to, string $body): array
    {
        $url = $this->facebookApi.$this->getPhoneId().'/messages';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getToken(),
                'Content-Type' => 'application/json',
            ])->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $body,
                    'preview_url' => true,
                ],
            ]);

            return [
                'status' => $response->status(),
                'content' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsAppGraphClient: send text failed', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'content' => $e->getMessage(),
            ];
        }
    }
}
