<?php

namespace App\Services\Onboarding;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogMode;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\Outcomes\PlaybookInstaller;
use App\Services\Platform\ActivationService;
use App\Services\Trust\AuditLogger;
use App\Services\WhatsApp\OrderInvoiceMessageTemplateService;
use App\Services\WhatsApp\WhatsAppGraphClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingMessageTemplatePackService;
use Modules\Wpbox\Models\Contact;

class VerticalGoLiveService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function packs(): array
    {
        return config('vertical-golive.packs', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastLaunch(Company $company): ?array
    {
        $raw = (string) $company->getConfig('vertical_golive_result', '');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function install(Company $company, string $vertical, bool $installPlaybook = true, ?string $testPhone = null): array
    {
        $pack = $this->packs()[$vertical] ?? null;
        if (! $pack) {
            return ['success' => false, 'message' => __('Unknown vertical pack.')];
        }

        $previous = session('company_id');
        session(['company_id' => $company->id]);

        try {
            $catalog = $this->seedCatalog($company, $pack);
            $sources = $this->seedBookingServices($company, $pack);
            $this->linkCatalogItemsToSources($catalog, $sources);

            $flow = app(FlowTemplateService::class)->install(
                $pack['flow_template'],
                $pack['name'],
                array_filter([
                    'catalog_id' => $catalog?->id,
                ])
            );

            $playbookResult = null;
            if ($installPlaybook && ! empty($pack['playbook'])) {
                try {
                    $playbookResult = app(PlaybookInstaller::class)->install(
                        $company,
                        $pack['playbook'],
                        false,
                        false
                    );
                } catch (\Throwable $e) {
                    Log::warning('Vertical go-live playbook install failed', [
                        'company_id' => $company->id,
                        'vertical' => $vertical,
                        'error' => $e->getMessage(),
                    ]);
                    $playbookResult = [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $templates = $this->publishMetaTemplates($company, $pack);
            $testMessage = $this->sendWelcomeMessage($company, $pack, $testPhone);

            $company->setConfig('activation_flow_installed', $flow ? 'yes' : 'no');
            $company->setConfig('vertical_golive_pack', $vertical);

            $checklist = $this->buildChecklist($flow, $catalog, $sources, $playbookResult, $templates, $testMessage);
            $waitingOnMeta = collect($checklist)
                ->where('status', 'waiting_on_meta')
                ->pluck('label')
                ->values()
                ->all();

            $result = [
                'success' => (bool) $flow,
                'message' => $this->launchMessage($pack, (bool) $flow, $waitingOnMeta),
                'vertical' => $vertical,
                'flow_id' => $flow?->id,
                'catalog_id' => $catalog?->id,
                'booking_source_ids' => collect($sources)->pluck('id')->values()->all(),
                'playbook' => $playbookResult,
                'templates' => $templates,
                'test_message' => $testMessage,
                'checklist' => $checklist,
                'waiting_on_meta' => $waitingOnMeta,
            ];

            $company->setConfig('vertical_golive_result', json_encode($result));

            app(AuditLogger::class)->log($company, 'vertical.golive', null, null, [
                'vertical' => $vertical,
                'flow_id' => $flow?->id,
                'catalog_id' => $catalog?->id,
                'waiting_on_meta' => $waitingOnMeta,
            ]);

            return $result;
        } finally {
            session(['company_id' => $previous]);
        }
    }

    /**
     * Re-submit Meta templates after Embedded Signup stores WABA credentials.
     *
     * @return array<string, mixed>
     */
    public function publishPendingMetaAssets(Company $company): array
    {
        $vertical = (string) $company->getConfig('vertical_golive_pack', '');
        $pack = $this->packs()[$vertical] ?? null;
        if (! $pack) {
            return ['success' => false, 'skipped' => true];
        }

        $previous = session('company_id');
        session(['company_id' => $company->id]);

        try {
            $templates = $this->publishMetaTemplates($company, $pack);
            $launch = $this->lastLaunch($company) ?? [];
            $launch['templates'] = $templates;

            if (isset($launch['checklist']) && is_array($launch['checklist'])) {
                foreach ($launch['checklist'] as $index => $item) {
                    if (($item['key'] ?? '') === 'templates') {
                        $launch['checklist'][$index]['status'] = $templates['status'] ?? 'waiting_on_meta';
                        $launch['checklist'][$index]['detail'] = $templates['booking']['message']
                            ?? $templates['invoice']['message']
                            ?? __('WhatsApp templates updated.');
                    }
                }
            }

            $launch['waiting_on_meta'] = collect($launch['checklist'] ?? [])
                ->where('status', 'waiting_on_meta')
                ->pluck('label')
                ->values()
                ->all();
            $company->setConfig('vertical_golive_result', json_encode($launch));

            return $templates;
        } finally {
            session(['company_id' => $previous]);
        }
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    protected function seedCatalog(Company $company, array $pack): ?ListCatalog
    {
        $items = $pack['catalog_items'] ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        if (! Schema::hasTable((new ListCatalog)->getTable())) {
            return null;
        }

        $name = (string) ($pack['catalog_name'] ?? $pack['name']);
        $normalizedItems = $this->normalizeCatalogItems($items, $pack);

        $existing = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();

        $attributes = [
            'company_id' => $company->id,
            'name' => $name,
            'catalog_mode' => $pack['catalog_mode'] ?? CatalogMode::SERVICE,
            'vertical' => $pack['catalog_vertical'] ?? 'general_service',
            'version' => 1,
            'items' => $normalizedItems,
            'columns' => [],
            'source' => 'manual',
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return ListCatalog::withoutGlobalScope(CompanyScope::class)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return list<Source>
     */
    protected function seedBookingServices(Company $company, array $pack): array
    {
        $services = $pack['booking_services'] ?? [];
        if (! is_array($services) || $services === [] || ! class_exists(Source::class)) {
            return [];
        }

        if (! Schema::hasTable((new Source)->getTable())) {
            return [];
        }

        $created = [];

        foreach ($services as $service) {
            $name = (string) ($service['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $duration = (int) ($service['default_duration_minutes'] ?? 30);
            $attributes = [
                'company_id' => $company->id,
                'name' => $name,
                'is_bookable' => true,
                'default_duration_minutes' => $duration,
                'duration_options' => [$duration],
                'buffer_minutes' => 0,
                'timezone' => 'UTC',
                'min_notice_hours' => 1,
                'max_advance_days' => 60,
                'working_hours' => [],
            ];

            $source = Source::queryForCompany($company->id)->where('name', $name)->first();

            if ($source) {
                $source->update($attributes);
                $created[] = $source->fresh();

                continue;
            }

            $created[] = Source::withoutGlobalScope(CompanyScope::class)->create($attributes);
        }

        return array_values(array_filter($created));
    }

    /**
     * @param  list<Source>  $sources
     */
    protected function linkCatalogItemsToSources(?ListCatalog $catalog, array $sources): void
    {
        if (! $catalog || $sources === []) {
            return;
        }

        $byName = collect($sources)->keyBy(fn (Source $source) => mb_strtolower($source->name));
        $items = $catalog->items ?? [];
        $changed = false;

        foreach ($items as $index => $item) {
            $title = mb_strtolower((string) ($item['title'] ?? ''));
            $source = $byName->get($title);
            if (! $source) {
                continue;
            }

            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $metadata['booking_source_id'] = $source->id;
            $items[$index]['metadata'] = $metadata;
            $changed = true;
        }

        if ($changed) {
            $catalog->update(['items' => $items]);
        }
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array<string, mixed>
     */
    protected function publishMetaTemplates(Company $company, array $pack): array
    {
        $result = [
            'booking' => null,
            'invoice' => null,
            'status' => 'skipped',
        ];

        if (! empty($pack['install_booking_templates'])) {
            try {
                $result['booking'] = app(BookingMessageTemplatePackService::class)->installForCompany($company);
            } catch (\Throwable $e) {
                $result['booking'] = [
                    'success' => false,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (! empty($pack['install_invoice_template'])) {
            try {
                $result['invoice'] = app(OrderInvoiceMessageTemplateService::class)->ensureForCompany($company);
            } catch (\Throwable $e) {
                $result['invoice'] = [
                    'ready' => false,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $result['status'] = $this->templateStatus($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array{status: string, message: string, contact_id?: int}
     */
    protected function sendWelcomeMessage(Company $company, array $pack, ?string $testPhone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $testPhone);
        if ($digits === '') {
            return ['status' => 'skipped', 'message' => __('No test number provided.')];
        }

        $phone = str_starts_with((string) $testPhone, '+') ? '+'.$digits : $digits;

        $contact = Contact::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $company->id,
                'phone' => $phone,
            ],
            [
                'name' => 'Go-live test',
                'enabled_ai_bot' => true,
            ]
        );

        $graph = new WhatsAppGraphClient($company);
        if (! $graph->hasMessagingCredentials()) {
            return [
                'status' => 'waiting_on_meta',
                'message' => __('Connect WhatsApp before a test message can send.'),
                'contact_id' => $contact->id,
            ];
        }

        $body = str_replace(
            ':company',
            (string) ($company->name ?? config('app.name')),
            (string) ($pack['welcome_message'] ?? 'Welcome to :company. Reply hello to get started.')
        );

        $sent = $graph->sendTextMessage($digits, $body);
        if (in_array((int) ($sent['status'] ?? 0), [200, 201], true)) {
            app(ActivationService::class)->markTestMessageSent($company);

            return [
                'status' => 'live',
                'message' => __('Test message sent.'),
                'contact_id' => $contact->id,
            ];
        }

        return [
            'status' => 'waiting_on_meta',
            'message' => __('WhatsApp did not accept the test message yet.'),
            'contact_id' => $contact->id,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $pack
     * @return list<array<string, mixed>>
     */
    protected function normalizeCatalogItems(array $items, array $pack): array
    {
        return array_values(array_map(function (array $item) use ($pack) {
            return [
                'id' => (string) ($item['id'] ?? str()->uuid()),
                'title' => (string) ($item['title'] ?? 'Item'),
                'price' => (float) ($item['price'] ?? 0),
                'category' => (string) ($item['category'] ?? $pack['name']),
                'metadata' => array_filter([
                    'duration_minutes' => $item['duration_minutes'] ?? null,
                ]),
            ];
        }, $items));
    }

    /**
     * @param  mixed  $flow
     * @param  mixed  $catalog
     * @param  list<Source>  $sources
     * @param  array<string, mixed>|null  $playbookResult
     * @param  array<string, mixed>  $templates
     * @param  array<string, mixed>  $testMessage
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    protected function buildChecklist($flow, $catalog, array $sources, ?array $playbookResult, array $templates, array $testMessage): array
    {
        $bookingTemplates = is_array($templates['booking'] ?? null) ? $templates['booking'] : null;
        $invoiceTemplate = is_array($templates['invoice'] ?? null) ? $templates['invoice'] : null;

        return [
            [
                'key' => 'flow',
                'label' => __('Automation flow'),
                'status' => $flow ? 'live' : 'failed',
                'detail' => $flow ? __('Installed and active.') : __('Flow template could not be installed.'),
            ],
            [
                'key' => 'catalog',
                'label' => __('Starter catalog'),
                'status' => $catalog ? 'live' : 'skipped',
                'detail' => $catalog
                    ? __('Seeded with sample items.')
                    : __('This pack does not include a catalog.'),
            ],
            [
                'key' => 'bookings',
                'label' => __('Bookable services'),
                'status' => $sources !== [] ? 'live' : 'skipped',
                'detail' => $sources !== []
                    ? __(':count services ready to book.', ['count' => count($sources)])
                    : __('This pack does not include appointment services.'),
            ],
            [
                'key' => 'playbook',
                'label' => __('Outcome journey'),
                'status' => ($playbookResult['success'] ?? false) ? 'live' : (is_array($playbookResult) ? 'failed' : 'skipped'),
                'detail' => $playbookResult['message'] ?? __('Not included in this pack.'),
            ],
            [
                'key' => 'templates',
                'label' => __('WhatsApp utility templates'),
                'status' => $templates['status'] ?? 'skipped',
                'detail' => $bookingTemplates['message']
                    ?? $invoiceTemplate['message']
                    ?? __('No Meta templates required for this pack.'),
            ],
            [
                'key' => 'test_message',
                'label' => __('Test message'),
                'status' => $testMessage['status'] ?? 'skipped',
                'detail' => $testMessage['message'] ?? __('Skipped.'),
            ],
        ];
    }

    /**
     * @param  array{booking?: mixed, invoice?: mixed}  $result
     */
    protected function templateStatus(array $result): string
    {
        $booking = is_array($result['booking'] ?? null) ? $result['booking'] : null;
        $invoice = is_array($result['invoice'] ?? null) ? $result['invoice'] : null;

        if ($booking === null && $invoice === null) {
            return 'skipped';
        }

        $bookingStatus = strtolower((string) ($booking['status'] ?? ''));
        $invoiceStatus = strtolower((string) ($invoice['status'] ?? ''));

        if (in_array($bookingStatus, ['missing_credentials', 'submit_failed', 'error'], true)
            || in_array($invoiceStatus, ['missing_credentials', 'submit_failed', 'error'], true)) {
            return 'waiting_on_meta';
        }

        if (($booking['success'] ?? false) || ($invoice['ready'] ?? false)) {
            $pending = str_contains(strtolower((string) ($booking['message'] ?? '')), 'pending')
                || $invoiceStatus === 'pending';

            return $pending ? 'waiting_on_meta' : 'live';
        }

        return 'waiting_on_meta';
    }

    /**
     * @param  array<string, mixed>  $pack
     * @param  list<string>  $waitingOnMeta
     */
    protected function launchMessage(array $pack, bool $flowInstalled, array $waitingOnMeta): string
    {
        if (! $flowInstalled) {
            return __('Could not install the flow template.');
        }

        if ($waitingOnMeta !== []) {
            return __(':name is installed. Waiting on Meta for: :items.', [
                'name' => $pack['name'],
                'items' => implode(', ', $waitingOnMeta),
            ]);
        }

        return __(':name is live. Remaining operator steps are only Meta approvals you cannot skip.', [
            'name' => $pack['name'],
        ]);
    }
}
