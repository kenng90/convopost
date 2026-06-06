<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Template;

class OrderInvoiceMessageTemplateService
{
    public const TEMPLATE_NAME = 'convocon_order_invoice';

    public const TEMPLATE_LANGUAGE = 'en';

    public const CONFIG_NAME_KEY = 'order_invoice_template_name';

    public const CONFIG_LANGUAGE_KEY = 'order_invoice_template_language';

    public const CONFIG_STATUS_KEY = 'order_invoice_template_status';

    /**
     * Ensure the order/invoice WhatsApp template exists for this company.
     *
     * @return array{ready: bool, status: string, message: string, template?: Template}
     */
    public function ensureForCompany(Company $company): array
    {
        $graph = new WhatsAppGraphClient($company);

        if (! $graph->hasTemplateCredentials()) {
            return [
                'ready' => false,
                'status' => 'missing_credentials',
                'message' => 'WhatsApp Business Account ID or access token is not configured.',
            ];
        }

        $templateName = $this->getTemplateName($company);
        $language = $this->getTemplateLanguage($company);

        $local = $this->findLocalTemplate($company, $templateName, $language);
        if ($local && strtoupper((string) $local->status) === 'APPROVED') {
            $this->storeTemplateStatus($company, 'APPROVED');

            return [
                'ready' => true,
                'status' => 'approved',
                'message' => 'Template is approved.',
                'template' => $local,
            ];
        }

        $remoteTemplates = $graph->listMessageTemplates($templateName);
        foreach ($remoteTemplates as $remote) {
            if (($remote['name'] ?? '') !== $templateName) {
                continue;
            }

            if (($remote['language'] ?? '') !== $language) {
                continue;
            }

            $synced = $this->syncRemoteTemplate($company, $remote);
            $remoteStatus = strtoupper((string) ($remote['status'] ?? 'PENDING'));

            $this->storeTemplateStatus($company, $remoteStatus);

            if ($remoteStatus === 'APPROVED') {
                return [
                    'ready' => true,
                    'status' => 'approved',
                    'message' => 'Template is approved.',
                    'template' => $synced,
                ];
            }

            return [
                'ready' => false,
                'status' => strtolower($remoteStatus),
                'message' => 'Template exists but is not approved yet. Outbound order messages outside the 24-hour window will work once Meta approves it.',
                'template' => $synced,
            ];
        }

        if ($local && in_array(strtoupper((string) $local->status), ['PENDING', 'IN_APPEAL'], true)) {
            $this->storeTemplateStatus($company, (string) $local->status);

            return [
                'ready' => false,
                'status' => 'pending',
                'message' => 'Template submission is pending Meta approval.',
                'template' => $local,
            ];
        }

        $submit = $graph->submitMessageTemplate($this->buildSubmissionPayload($templateName, $language));
        if (! in_array($submit['status'], [200, 201], true)) {
            $error = is_array($submit['content'])
                ? ($submit['content']['error']['message'] ?? json_encode($submit['content']))
                : (string) $submit['content'];

            Log::warning('OrderInvoiceMessageTemplateService: submit failed', [
                'company_id' => $company->id,
                'status' => $submit['status'],
                'error' => $error,
            ]);

            return [
                'ready' => false,
                'status' => 'submit_failed',
                'message' => 'Could not create WhatsApp template: '.$error,
            ];
        }

        $content = is_array($submit['content']) ? $submit['content'] : [];
        $template = $this->syncRemoteTemplate($company, [
            'id' => $content['id'] ?? null,
            'name' => $templateName,
            'category' => $content['category'] ?? 'UTILITY',
            'language' => $language,
            'status' => $content['status'] ?? 'PENDING',
            'components' => $this->buildSubmissionPayload($templateName, $language)['components'],
        ]);

        $this->storeTemplateStatus($company, (string) ($template->status ?? 'PENDING'));

        return [
            'ready' => false,
            'status' => 'pending',
            'message' => 'Template submitted to Meta and is pending approval.',
            'template' => $template,
        ];
    }

    public function getTemplateName(Company $company): string
    {
        return (string) ($company->getConfig(self::CONFIG_NAME_KEY, self::TEMPLATE_NAME) ?: self::TEMPLATE_NAME);
    }

    public function getTemplateLanguage(Company $company): string
    {
        return (string) ($company->getConfig(self::CONFIG_LANGUAGE_KEY, self::TEMPLATE_LANGUAGE) ?: self::TEMPLATE_LANGUAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSubmissionPayload(string $templateName, string $language): array
    {
        return [
            'name' => $templateName,
            'category' => 'UTILITY',
            'language' => $language,
            'allow_category_change' => true,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => "Hello {{1}},\n\nYour order *{{2}}* from our catalog is ready.\n\n{{3}}\n\n*Total:* {{4}}\n\nView and pay your invoice:\n{{5}}\n\nThank you for your business!",
                    'example' => [
                        'body_text' => [[
                            'Jane Customer',
                            'INV-1001',
                            '• Sample product (x1) - KES 500.00',
                            'KES 500.00',
                            'https://example.com/catalog/pay/demo',
                        ]],
                    ],
                ],
            ],
        ];
    }

    protected function findLocalTemplate(Company $company, string $name, string $language): ?Template
    {
        return Template::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->where('language', $language)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    protected function syncRemoteTemplate(Company $company, array $remote): Template
    {
        $components = $remote['components'] ?? [];
        if (is_array($components)) {
            $components = json_encode($components);
        }

        $data = [
            'name' => $remote['name'] ?? self::TEMPLATE_NAME,
            'category' => $remote['category'] ?? 'UTILITY',
            'language' => $remote['language'] ?? self::TEMPLATE_LANGUAGE,
            'status' => $remote['status'] ?? 'PENDING',
            'company_id' => $company->id,
            'components' => $components,
            'deleted_at' => null,
        ];

        if (! empty($remote['id'])) {
            $data['id'] = $remote['id'];
            Template::withoutGlobalScope(CompanyScope::class)->upsert(
                $data,
                ['id'],
                ['components', 'status', 'deleted_at', 'category', 'name', 'language', 'company_id']
            );

            return Template::withoutGlobalScope(CompanyScope::class)->find($remote['id']);
        }

        return Template::withoutGlobalScope(CompanyScope::class)->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => $data['name'],
                'language' => $data['language'],
            ],
            $data
        );
    }

    protected function storeTemplateStatus(Company $company, string $status): void
    {
        $company->setConfig(self::CONFIG_STATUS_KEY, strtoupper($status));
    }
}
