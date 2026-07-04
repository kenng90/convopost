<?php

namespace App\Services\Campaign;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;

class ApiCampaignService
{
    public function __construct(
        private readonly CampaignTemplateVariablesParser $variablesParser,
        private readonly CampaignMediaService $mediaService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Company $company, array $payload): Campaign
    {
        $this->validatePayload($company, $payload);

        $campaign = Campaign::create($this->attributes($company, $payload));

        $this->mediaService->attachFromPayload($campaign, $payload);

        return $campaign->fresh(['template']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Campaign $campaign, array $payload): Campaign
    {
        if (! $campaign->is_api) {
            throw ValidationException::withMessages([
                'campaign' => [__('Only API campaigns can be updated here.')],
            ]);
        }

        $company = $campaign->company ?? Company::findOrFail($campaign->company_id);
        $this->validatePayload($company, $payload);

        $campaign->update($this->attributes($company, $payload, $campaign));
        $this->mediaService->attachFromPayload($campaign, $payload);

        return $campaign->fresh(['template']);
    }

    public function toggleActive(Campaign $campaign): Campaign
    {
        if (! $campaign->is_api) {
            throw ValidationException::withMessages([
                'campaign' => [__('Only API campaigns can be toggled here.')],
            ]);
        }

        $active = ! $campaign->is_active;
        $campaign->update([
            'is_active' => $active,
            'status' => $active ? Campaign::STATUS_ACTIVE : Campaign::STATUS_INACTIVE,
        ]);

        return $campaign->fresh();
    }

    public function clone(Campaign $campaign): Campaign
    {
        if (! $campaign->is_api) {
            throw ValidationException::withMessages([
                'campaign' => [__('Only API campaigns can be cloned here.')],
            ]);
        }

        $clone = $campaign->cloneAsDraft($campaign->name.' (copy)');
        $clone->update([
            'is_api' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'broadcast_type' => null,
            'group_id' => null,
            'segment_id' => null,
            'contact_id' => null,
            'timestamp_for_delivery' => null,
        ]);

        return $clone->fresh(['template']);
    }

    /**
     * @return array<string, mixed>
     */
    public function samplePayload(Campaign $campaign, ?string $token = null): array
    {
        $data = $this->sampleDataFromVariables($campaign);

        return [
            'token' => $token ?: 'YOUR_API_TOKEN',
            'campaign_id' => $campaign->id,
            'phone' => '+254700000000',
            'data' => $data,
        ];
    }

    public function sampleCurl(Campaign $campaign, ?string $token = null): string
    {
        $payload = $this->samplePayload($campaign, $token);
        $endpoint = rtrim(config('app.url'), '/').'/api/wpbox/sendcampaigns';
        $dataJson = json_encode($payload['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "curl -X POST '{$endpoint}' \\\n"
            ."  -H 'Accept: application/json' \\\n"
            ."  -H 'Content-Type: application/json' \\\n"
            .'  -d \''.json_encode([
                'token' => $payload['token'],
                'campaign_id' => $payload['campaign_id'],
                'phone' => $payload['phone'],
                'data' => $payload['data'],
            ], JSON_UNESCAPED_SLASHES).'\'';
    }

    /**
     * @return array<int, array{section: string, id: string, path: string}>
     */
    public function apiVariablePaths(Campaign $campaign): array
    {
        $match = json_decode($campaign->variables_match, true) ?? [];
        $values = json_decode($campaign->variables, true) ?? [];
        $paths = [];

        foreach (['header', 'body'] as $section) {
            if (! isset($match[$section]) || ! is_array($match[$section])) {
                continue;
            }

            foreach ($match[$section] as $id => $fieldId) {
                if ((string) $fieldId !== '-3') {
                    continue;
                }

                $paths[] = [
                    'section' => $section,
                    'id' => (string) $id,
                    'path' => (string) ($values[$section][$id] ?? 'field'),
                ];
            }
        }

        if (isset($match['buttons']) && is_array($match['buttons'])) {
            foreach ($match['buttons'] as $buttonKey => $buttonVars) {
                if (! is_array($buttonVars)) {
                    continue;
                }

                foreach ($buttonVars as $id => $fieldId) {
                    if ((string) $fieldId !== '-3') {
                        continue;
                    }

                    $paths[] = [
                        'section' => 'buttons.'.$buttonKey,
                        'id' => (string) $id,
                        'path' => (string) ($values['buttons'][$buttonKey][$id] ?? 'field'),
                    ];
                }
            }
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<int, string>
     */
    public function missingApiVariables(Campaign $campaign, ?array $data): array
    {
        $missing = [];

        foreach ($this->apiVariablePaths($campaign) as $variable) {
            if (! $this->dataHasPath($data ?? [], $variable['path'])) {
                $missing[] = $variable['path'];
            }
        }

        return $missing;
    }

    public function assertSendable(Campaign $campaign): void
    {
        if (! $campaign->is_api) {
            abort(422, 'campaign_id must reference an API campaign.');
        }

        if (! $campaign->is_active || $campaign->status === Campaign::STATUS_INACTIVE) {
            abort(422, 'This API campaign is inactive.');
        }

        if (! $campaign->template_id) {
            abort(422, 'This API campaign has no WhatsApp template.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributes(Company $company, array $payload, ?Campaign $existing = null): array
    {
        return [
            'company_id' => $company->id,
            'name' => $payload['name'] ?? ($existing?->name ?? 'api_campaign_'.now()->format('YmdHis')),
            'template_id' => $payload['template_id'],
            'variables' => json_encode($payload['paramvalues'] ?? []),
            'variables_match' => json_encode($payload['parammatch'] ?? []),
            'is_api' => true,
            'is_bot' => false,
            'is_reminder' => false,
            'is_active' => $payload['is_active'] ?? $existing?->is_active ?? true,
            'status' => ($payload['is_active'] ?? $existing?->is_active ?? true)
                ? Campaign::STATUS_ACTIVE
                : Campaign::STATUS_INACTIVE,
            'broadcast_type' => null,
            'group_id' => null,
            'segment_id' => null,
            'contact_id' => null,
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'channel_template_key' => null,
            'timestamp_for_delivery' => null,
            'total_contacts' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(Company $company, array $payload): void
    {
        if (empty($payload['template_id'])) {
            throw ValidationException::withMessages([
                'template_id' => [__('Select a WhatsApp template.')],
            ]);
        }

        $template = Template::where('company_id', $company->id)->find($payload['template_id']);

        if (! $template) {
            throw ValidationException::withMessages([
                'template_id' => [__('Invalid WhatsApp template.')],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleDataFromVariables(Campaign $campaign): array
    {
        $data = [];

        foreach ($this->apiVariablePaths($campaign) as $variable) {
            $this->setPathValue($data, $variable['path'], 'example_'.$variable['id']);
        }

        if ($data === []) {
            $data = ['customer_name' => 'Jane Doe', 'order' => ['id' => '1001']];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dataHasPath(array $data, string $path): bool
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return false;
            }

            $current = $current[$key];
        }

        return $current !== null && $current !== '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setPathValue(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $index => $key) {
            if ($index === count($keys) - 1) {
                $current[$key] = $value;

                return;
            }

            if (! isset($current[$key]) || ! is_array($current[$key])) {
                $current[$key] = [];
            }

            $current = &$current[$key];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFromRequest(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'template_id' => $request->input('template_id'),
            'paramvalues' => $request->input('paramvalues', []),
            'parammatch' => $request->input('parammatch', []),
            'pdf' => $request->file('pdf'),
            'imageupload' => $request->file('imageupload'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
