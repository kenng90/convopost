<?php

namespace App\Services\Campaign\Templates;

use App\Models\Company;
use Illuminate\Support\Collection;

class SmsTemplateProvider
{
    /**
     * @return Collection<string, string>
     */
    public function options(Company $company): Collection
    {
        $options = collect(['custom' => __('Custom message')]);

        foreach (range(1, 5) as $index) {
            $body = $company->getConfig("SMS_TEMPLATE_{$index}_BODY", '');
            if (! empty(trim($body))) {
                $label = $company->getConfig("SMS_TEMPLATE_{$index}_NAME", "SMS template {$index}");
                $options->put((string) $index, $label);
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Company $company, string $key): ?array
    {
        if ($key === 'custom') {
            return [
                'type' => 'sms',
                'key' => 'custom',
                'name' => __('Custom message'),
                'body' => '',
            ];
        }

        $body = $company->getConfig("SMS_TEMPLATE_{$key}_BODY", '');

        if (empty(trim($body))) {
            return null;
        }

        return [
            'type' => 'sms',
            'key' => $key,
            'name' => $company->getConfig("SMS_TEMPLATE_{$key}_NAME", "SMS template {$key}"),
            'body' => $body,
        ];
    }

    public function creditAction(): string
    {
        return 'send_sms_message';
    }
}
