<?php

namespace App\Services;

use App\Models\Company;
use App\Models\WhatsappFlow;

class WhatsappFlowReadinessService
{
    public function __construct(
        private WhatsappMetaFlowService $meta
    ) {
    }

    /**
     * @return array{
     *     ready: bool,
     *     live: bool,
     *     can_publish: bool,
     *     steps: list<array{key: string, title: string, completed: bool, help: string}>,
     *     completed: int,
     *     total: int
     * }
     */
    public function forForm(WhatsappFlow $flow): array
    {
        $company = $flow->company ?? Company::find($flow->company_id);
        $credentials = $company ? $this->meta->getCredentialsFromCompany($company) : null;
        $phoneOk = $company && filled($company->getConfig('whatsapp_phone_number_id'));
        $tokenOk = is_array($credentials) && filled($credentials['access_token'] ?? null) && filled($credentials['business_account_id'] ?? null);
        $hasScreens = $this->hasInputScreens($flow);
        $needsEndpoint = $this->builderLikelyNeedsDataExchange($flow);
        $hasPrivateKey = $company && filled($company->getConfig('whatsapp_flow_private_key'));
        $encryptionOk = ! $needsEndpoint || $hasPrivateKey;
        $isLive = filled($flow->meta_flow_id);

        $steps = [
            [
                'key' => 'credentials',
                'title' => 'WhatsApp API credentials',
                'completed' => (bool) ($tokenOk && $phoneOk),
                'help' => 'Set permanent access token, WABA ID, and phone number ID in company WhatsApp settings.',
            ],
            [
                'key' => 'screens',
                'title' => 'Form has screens with fields',
                'completed' => $hasScreens,
                'help' => 'Add at least one screen with an input field in the form builder.',
            ],
            [
                'key' => 'encryption',
                'title' => 'Encryption key (endpoint forms)',
                'completed' => $encryptionOk,
                'help' => $needsEndpoint
                    ? 'This form appears to need data_exchange. Generate and upload the WhatsApp Flow encryption key.'
                    : 'Not required for navigate-only forms.',
            ],
            [
                'key' => 'live',
                'title' => 'Live on WhatsApp',
                'completed' => $isLive,
                'help' => 'Publish the form to Meta so it can be sent and used in automations.',
            ],
        ];

        $completed = count(array_filter($steps, fn (array $step) => $step['completed']));
        $canPublish = $tokenOk && $phoneOk && $hasScreens && $encryptionOk;

        return [
            'ready' => $canPublish && $isLive,
            'live' => $isLive,
            'can_publish' => $canPublish,
            'steps' => $steps,
            'completed' => $completed,
            'total' => count($steps),
        ];
    }

    /**
     * Company-level readiness (no specific form).
     *
     * @return array{ready: bool, steps: list<array{key: string, title: string, completed: bool, help: string}>}
     */
    public function forCompany(?Company $company): array
    {
        if (! $company) {
            return [
                'ready' => false,
                'steps' => [[
                    'key' => 'company',
                    'title' => 'Company context',
                    'completed' => false,
                    'help' => 'Select an active company.',
                ]],
            ];
        }

        $credentials = $this->meta->getCredentialsFromCompany($company);
        $tokenOk = is_array($credentials) && filled($credentials['access_token'] ?? null) && filled($credentials['business_account_id'] ?? null);
        $phoneOk = filled($company->getConfig('whatsapp_phone_number_id'));
        $hasPrivateKey = filled($company->getConfig('whatsapp_flow_private_key'));

        $steps = [
            [
                'key' => 'credentials',
                'title' => 'WhatsApp API credentials',
                'completed' => (bool) ($tokenOk && $phoneOk),
                'help' => 'Set permanent access token, WABA ID, and phone number ID.',
            ],
            [
                'key' => 'encryption',
                'title' => 'Flow encryption key',
                'completed' => $hasPrivateKey,
                'help' => 'Recommended for dynamic forms; required when using data_exchange.',
            ],
        ];

        return [
            'ready' => $tokenOk && $phoneOk,
            'steps' => $steps,
        ];
    }

    public function companyCanAutoPublish(?Company $company): bool
    {
        return (bool) ($this->forCompany($company)['ready'] ?? false);
    }

    private function hasInputScreens(WhatsappFlow $flow): bool
    {
        $screens = $flow->flow_json['screens'] ?? [];
        if (! is_array($screens) || $screens === []) {
            return false;
        }

        foreach ($screens as $screen) {
            $fields = $screen['fields'] ?? $screen['components'] ?? [];
            if (is_array($fields) && $fields !== []) {
                return true;
            }
        }

        return false;
    }

    private function builderLikelyNeedsDataExchange(WhatsappFlow $flow): bool
    {
        $json = json_encode($flow->flow_json ?? []);
        if (! is_string($json) || $json === '') {
            return false;
        }

        return str_contains($json, 'data_exchange')
            || str_contains($json, 'data_source')
            || str_contains($json, 'refresh_on_back');
    }
}
