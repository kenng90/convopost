<?php

namespace App\Services\Campaign\Templates;

use App\Models\Company;
use Illuminate\Support\Collection;

class EmailTemplateProvider
{
    /**
     * @return Collection<string, string>
     */
    public function options(Company $company): Collection
    {
        $options = collect();

        foreach (range(1, 5) as $index) {
            $subject = $company->getConfig("EMAIL_TEMPLATE_{$index}_SUBJECT", '');
            $content = $company->getConfig("EMAIL_TEMPLATE_{$index}_BODY", '');

            if (! empty(trim($subject)) && ! empty(trim($content))) {
                $name = implode(' ', array_slice(explode(' ', str_replace(["\n", "\r"], ' ', $subject)), 0, 4));
                $options->put((string) $index, $name);
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Company $company, string $key): ?array
    {
        $subject = $company->getConfig("EMAIL_TEMPLATE_{$key}_SUBJECT", '');
        $body = $company->getConfig("EMAIL_TEMPLATE_{$key}_BODY", '');

        if (empty(trim($subject)) || empty(trim($body))) {
            return null;
        }

        return [
            'type' => 'email',
            'key' => $key,
            'name' => $subject,
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public function creditAction(): string
    {
        return 'send_email_message';
    }
}
