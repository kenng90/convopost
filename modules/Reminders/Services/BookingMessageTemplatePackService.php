<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\Platform\ActivationService;
use App\Services\WhatsApp\WhatsAppGraphClient;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;

class BookingMessageTemplatePackService
{
    public const CONFIG_INSTALLED_KEY = 'booking_message_pack_installed';

    public function __construct(
        private readonly ActivationService $activationService,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return config('booking-message-templates', []);
    }

    /**
     * @return array{
     *     installed: bool,
     *     whatsapp_ready: bool,
     *     present_count: int,
     *     total_count: int,
     *     was_used_before: bool,
     *     show_install_prompt: bool,
     *     show_repair_prompt: bool,
     *     templates: array<int, array<string, mixed>>
     * }
     */
    public function statusForCompany(Company $company): array
    {
        $whatsappReady = $this->activationService->isWhatsappConnected($company);
        $wasUsedBefore = $company->getConfig(self::CONFIG_INSTALLED_KEY, 'no') === 'yes';
        $templates = [];
        $presentCount = 0;
        $definitions = $this->definitions();
        $totalCount = count($definitions);

        foreach ($definitions as $key => $definition) {
            $template = $this->findLocalTemplateForDefinition($company, $key, $definition);
            $campaign = $this->findPackCampaign($company, $key, $definition);
            $isComplete = $template !== null && $campaign !== null;

            if ($isComplete) {
                $presentCount++;
            }

            $templates[] = [
                'key' => $key,
                'template_name' => $this->resolvedTemplateName($company, $key, $definition),
                'campaign_name' => $definition['campaign_name'],
                'role' => $definition['role'] ?? null,
                'template_status' => $template?->status,
                'campaign_id' => $campaign?->id,
                'complete' => $isComplete,
                'missing_template' => $template === null,
                'missing_campaign' => $campaign === null,
            ];
        }

        $allPresent = $presentCount === $totalCount && $totalCount > 0;
        $anyPresent = $presentCount > 0;

        return [
            'installed' => $allPresent,
            'whatsapp_ready' => $whatsappReady,
            'present_count' => $presentCount,
            'total_count' => $totalCount,
            'was_used_before' => $wasUsedBefore,
            'show_install_prompt' => $whatsappReady && ! $allPresent && ! $wasUsedBefore && ! $anyPresent,
            'show_repair_prompt' => $whatsappReady && ! $allPresent && ($wasUsedBefore || $anyPresent),
            'templates' => $templates,
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     results: array<int, array<string, mixed>>
     * }
     */
    public function installForCompany(Company $company): array
    {
        $graph = new WhatsAppGraphClient($company);

        if (! $graph->hasTemplateCredentials()) {
            return [
                'success' => false,
                'status' => 'missing_credentials',
                'message' => __('Connect WhatsApp before installing booking message templates.'),
                'results' => [],
            ];
        }

        $statusBefore = $this->statusForCompany($company);
        $isRepair = ($statusBefore['was_used_before'] ?? false) || ($statusBefore['present_count'] ?? 0) > 0;

        $results = [];
        $failures = 0;

        foreach ($this->definitions() as $key => $definition) {
            if ($this->isDefinitionComplete($company, $key, $definition)) {
                $template = $this->findLocalTemplateForDefinition($company, $key, $definition);
                $campaign = $this->findPackCampaign($company, $key, $definition);
                $results[] = [
                    'key' => $key,
                    'status' => strtolower((string) ($template?->status ?? 'approved')),
                    'template_id' => $template?->id,
                    'campaign_id' => $campaign?->id,
                    'skipped' => true,
                ];

                continue;
            }

            $result = $this->ensureDefinition($company, $graph, $key, $definition);
            $results[] = $result;

            if (($result['status'] ?? '') === 'submit_failed') {
                $failures++;
            }
        }

        if ($failures === count($this->definitions())) {
            return [
                'success' => false,
                'status' => 'submit_failed',
                'message' => __('Could not submit booking message templates to Meta. Check your WhatsApp connection and try again.'),
                'results' => $results,
            ];
        }

        $company->setConfig(self::CONFIG_INSTALLED_KEY, 'yes');

        $pending = collect($results)->whereIn('status', ['pending', 'in_appeal'])->count();
        $approved = collect($results)->where('status', 'approved')->count();
        $repaired = collect($results)
            ->filter(fn (array $result) => empty($result['skipped']) && in_array($result['status'] ?? '', ['approved', 'pending', 'in_appeal'], true))
            ->count();
        $submitFailures = collect($results)->where('status', 'submit_failed');

        if ($isRepair) {
            if ($pending > 0 && $approved === 0) {
                $message = __('Booking message pack repaired. Recreated :count template(s); Meta approval is pending before messages can send.', [
                    'count' => $repaired,
                ]);
            } elseif ($pending > 0) {
                $message = __('Booking message pack repaired. :approved approved, :pending pending Meta review.', [
                    'approved' => $approved,
                    'pending' => $pending,
                ]);
            } else {
                $message = __('Booking message pack repaired. Reattach campaigns on services or events if needed.');
            }
        } elseif ($pending > 0 && $approved === 0) {
            $message = __('Booking message templates submitted. They will work once Meta approves them — attach them on your services or events under Client notifications.');
        } elseif ($pending > 0) {
            $message = __('Booking message pack installed. :approved template(s) approved, :pending still pending Meta review.', [
                'approved' => $approved,
                'pending' => $pending,
            ]);
        } else {
            $message = __('Booking message pack installed. Attach the templates on your services or events to start sending confirmations and reminders.');
        }

        if ($submitFailures->isNotEmpty()) {
            $message .= ' '.__('Some templates could not be submitted: :errors', [
                'errors' => $submitFailures->pluck('error')->filter()->implode(' '),
            ]);
        }

        return [
            'success' => true,
            'status' => $isRepair ? 'repaired' : 'installed',
            'message' => $message,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function ensureDefinition(Company $company, WhatsAppGraphClient $graph, string $key, array $definition): array
    {
        $language = $definition['language'];

        $remote = $this->findRemotePackTemplateForDefinition($graph, $company, $key, $definition);
        if ($remote !== null) {
            $template = $this->syncRemoteTemplate($company, $remote);
            $campaign = $this->ensureCampaign($company, $key, $definition, $template);

            return [
                'key' => $key,
                'status' => strtolower((string) ($remote['status'] ?? 'pending')),
                'template_id' => $template->id,
                'campaign_id' => $campaign->id,
                'template_name' => $template->name,
            ];
        }

        $template = $this->findLocalTemplateForDefinition($company, $key, $definition);

        if ($template && strtoupper((string) $template->status) === 'APPROVED') {
            $campaign = $this->ensureCampaign($company, $key, $definition, $template);

            return [
                'key' => $key,
                'status' => 'approved',
                'template_id' => $template->id,
                'campaign_id' => $campaign->id,
                'template_name' => $template->name,
            ];
        }

        if ($template && in_array(strtoupper((string) $template->status), ['PENDING', 'IN_APPEAL'], true)) {
            $campaign = $this->ensureCampaign($company, $key, $definition, $template);

            return [
                'key' => $key,
                'status' => 'pending',
                'template_id' => $template->id,
                'campaign_id' => $campaign->id,
                'template_name' => $template->name,
            ];
        }

        $submission = $this->submitPackTemplateToMeta($company, $graph, $key, $definition);
        if (! ($submission['success'] ?? false)) {
            Log::warning('BookingMessageTemplatePackService: submit failed', [
                'company_id' => $company->id,
                'template_key' => $key,
                'error' => $submission['error'] ?? null,
            ]);

            return [
                'key' => $key,
                'status' => 'submit_failed',
                'error' => $submission['error'] ?? __('Unknown Meta error'),
            ];
        }

        $content = is_array($submission['submit']['content'] ?? null) ? $submission['submit']['content'] : [];
        $templateName = (string) ($submission['name'] ?? $definition['template_name']);
        $template = $this->syncRemoteTemplate($company, [
            'id' => $content['id'] ?? null,
            'name' => $templateName,
            'category' => $content['category'] ?? $definition['category'],
            'language' => $language,
            'status' => $content['status'] ?? 'PENDING',
            'components' => $this->buildSubmissionPayload($definition, $templateName)['components'],
        ]);
        $campaign = $this->ensureCampaign($company, $key, $definition, $template);

        return [
            'key' => $key,
            'status' => 'pending',
            'template_id' => $template->id,
            'campaign_id' => $campaign->id,
            'template_name' => $template->name,
        ];
    }

    /**
     * @return array{success: bool, name?: string, submit?: array<string, mixed>, error?: string}
     */
    protected function submitPackTemplateToMeta(
        Company $company,
        WhatsAppGraphClient $graph,
        string $key,
        array $definition,
    ): array {
        $lastError = __('Unknown Meta error');

        foreach ($this->templateNameCandidates($company, $key, $definition) as $candidateName) {
            $graph->deleteMessageTemplate($candidateName);

            $submit = $graph->submitMessageTemplate(
                $this->buildSubmissionPayload($definition, $candidateName)
            );

            if (in_array($submit['status'], [200, 201], true)) {
                if ($candidateName !== $definition['template_name']) {
                    $company->setConfig($this->templateNameConfigKey($key), $candidateName);
                }

                return [
                    'success' => true,
                    'name' => $candidateName,
                    'submit' => $submit,
                ];
            }

            $lastError = $this->extractMetaError($submit['content'] ?? '');

            if (! $this->shouldRetryWithAlternateName($submit)) {
                break;
            }
        }

        return [
            'success' => false,
            'error' => $lastError,
        ];
    }

    protected function templateNameConfigKey(string $key): string
    {
        return 'booking_template_name_'.$key;
    }

    protected function resolvedTemplateName(Company $company, string $key, array $definition): string
    {
        $stored = trim((string) $company->getConfig($this->templateNameConfigKey($key), ''));

        return $stored !== '' ? $stored : $definition['template_name'];
    }

    /**
     * @return array<int, string>
     */
    protected function templateNameCandidates(Company $company, string $key, array $definition): array
    {
        $base = $definition['template_name'];
        $stored = trim((string) $company->getConfig($this->templateNameConfigKey($key), ''));

        return array_values(array_unique(array_filter([
            $stored !== '' ? $stored : null,
            $base,
            $base.'_v2',
            $base.'_repair',
        ])));
    }

    protected function findLocalTemplateForDefinition(
        Company $company,
        string $key,
        array $definition,
        bool $withTrashed = false,
    ): ?Template {
        foreach ($this->templateNameCandidates($company, $key, $definition) as $name) {
            $template = $this->findLocalTemplate($company, $name, $definition['language'], $withTrashed);

            if ($template) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findRemotePackTemplateForDefinition(
        WhatsAppGraphClient $graph,
        Company $company,
        string $key,
        array $definition,
    ): ?array {
        foreach ($this->templateNameCandidates($company, $key, $definition) as $name) {
            $remote = $this->findRemotePackTemplate($graph, $name, $definition['language']);

            if ($remote !== null) {
                return $remote;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|string  $content
     */
    protected function extractMetaError(array|string $content): string
    {
        if (! is_array($content)) {
            return (string) $content;
        }

        $error = $content['error'] ?? [];

        return trim(implode(' — ', array_filter([
            $error['error_user_title'] ?? null,
            $error['error_user_msg'] ?? null,
            $error['message'] ?? null,
        ]))) ?: json_encode($content);
    }

    /**
     * @param  array{status: int, content: array<string, mixed>|string}  $submit
     */
    protected function shouldRetryWithAlternateName(array $submit): bool
    {
        if (in_array($submit['status'], [400, 409], true)) {
            return true;
        }

        if (! is_array($submit['content'] ?? null)) {
            return false;
        }

        $error = $submit['content']['error'] ?? [];
        $haystack = strtolower(implode(' ', array_filter([
            $error['message'] ?? null,
            $error['error_user_title'] ?? null,
            $error['error_user_msg'] ?? null,
        ])));

        foreach (['invalid parameter', 'duplicate', 'already exists', 'being deleted', 'template name'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function isDefinitionComplete(Company $company, string $key, array $definition): bool
    {
        return $this->findLocalTemplateForDefinition($company, $key, $definition) !== null
            && $this->findPackCampaign($company, $key, $definition) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findRemotePackTemplate(WhatsAppGraphClient $graph, string $templateName, string $language): ?array
    {
        foreach ($graph->listMessageTemplates($templateName) as $remote) {
            if (($remote['name'] ?? '') === $templateName && ($remote['language'] ?? '') === $language) {
                return $remote;
            }
        }

        foreach ($graph->listMessageTemplates() as $remote) {
            if (($remote['name'] ?? '') === $templateName && ($remote['language'] ?? '') === $language) {
                return $remote;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function buildSubmissionPayload(array $definition, ?string $templateName = null): array
    {
        return [
            'name' => $templateName ?? $definition['template_name'],
            'category' => $definition['category'] ?? 'UTILITY',
            'language' => $definition['language'] ?? 'en',
            'allow_category_change' => true,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $definition['body'],
                    'example' => [
                        'body_text' => [array_values($definition['example'] ?? [])],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function ensureCampaign(Company $company, string $key, array $definition, Template $template): Campaign
    {
        $existing = $this->findPackCampaign($company, $key, $definition);

        $variablesMatch = $definition['variables_match'] ?? [];
        $variables = $this->buildPlaceholderVariables($variablesMatch);

        $attributes = [
            'company_id' => $company->id,
            'name' => $definition['campaign_name'],
            'template_id' => $template->id,
            'is_reminder' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'variables' => json_encode($variables),
            'variables_match' => json_encode($variablesMatch),
            'channel' => Campaign::CHANNEL_WHATSAPP,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return Campaign::withoutGlobalScope(CompanyScope::class)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $variablesMatch
     * @return array<string, mixed>
     */
    protected function buildPlaceholderVariables(array $variablesMatch): array
    {
        $variables = [];

        foreach ($variablesMatch as $section => $matches) {
            if (! is_array($matches)) {
                continue;
            }

            foreach ($matches as $slot => $match) {
                if (is_array($match)) {
                    foreach ($match as $nestedSlot => $nestedMatch) {
                        $variables[$section][$slot][$nestedSlot] = 'placeholder';
                    }

                    continue;
                }

                $variables[$section][$slot] = 'placeholder';
            }
        }

        return $variables;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function findPackCampaign(Company $company, string $key, array $definition): ?Campaign
    {
        $template = $this->findLocalTemplateForDefinition($company, $key, $definition);

        if (! $template) {
            return Campaign::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $company->id)
                ->where('is_reminder', true)
                ->where('name', $definition['campaign_name'])
                ->first();
        }

        return Campaign::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('is_reminder', true)
            ->where(function ($query) use ($definition, $template) {
                $query->where('template_id', $template->id)
                    ->orWhere('name', $definition['campaign_name']);
            })
            ->first();
    }

    protected function findLocalTemplate(Company $company, string $name, string $language, bool $withTrashed = false): ?Template
    {
        $query = Template::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->where('language', $language);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
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

        $name = (string) ($remote['name'] ?? '');
        $language = (string) ($remote['language'] ?? 'en');
        $newId = ! empty($remote['id']) && is_numeric($remote['id']) ? (int) $remote['id'] : null;

        $existing = Template::withoutGlobalScope(CompanyScope::class)
            ->withTrashed()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->where('language', $language)
            ->first();

        $data = [
            'name' => $name,
            'category' => $remote['category'] ?? 'UTILITY',
            'language' => $language,
            'status' => $remote['status'] ?? 'PENDING',
            'company_id' => $company->id,
            'components' => $components,
            'deleted_at' => null,
        ];

        if ($existing && $newId && (int) $existing->id !== $newId) {
            Template::withoutGlobalScope(CompanyScope::class)->upsert(
                array_merge($data, ['id' => $newId]),
                ['id'],
                ['components', 'status', 'deleted_at', 'category', 'name', 'language', 'company_id']
            );

            Campaign::withoutGlobalScope(CompanyScope::class)
                ->where('template_id', $existing->id)
                ->update(['template_id' => $newId]);

            $existing->forceDelete();

            $template = Template::withoutGlobalScope(CompanyScope::class)->find($newId);
            if ($template) {
                return $template;
            }
        }

        if ($newId) {
            $data['id'] = $newId;
            Template::withoutGlobalScope(CompanyScope::class)->upsert(
                $data,
                ['id'],
                ['components', 'status', 'deleted_at', 'category', 'name', 'language', 'company_id']
            );

            $template = Template::withoutGlobalScope(CompanyScope::class)->find($newId);
            if ($template) {
                return $template;
            }
        }

        return Template::withoutGlobalScope(CompanyScope::class)
            ->withTrashed()
            ->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $data['name'],
                    'language' => $data['language'],
                ],
                $data
            )
            ->tap(function (Template $template) {
                if ($template->trashed()) {
                    $template->restore();
                }
            });
    }
}
