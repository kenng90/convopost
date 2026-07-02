<?php

namespace App\Services\Campaign\Templates;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Wpbox\Models\Template;

class WhatsAppTemplateProvider
{
    /**
     * @return Collection<int, string>
     */
    public function options(Company $company): Collection
    {
        return Template::query()
            ->where('company_id', $company->id)
            ->where('status', 'APPROVED')
            ->get()
            ->mapWithKeys(fn (Template $t) => [$t->id => $t->name.' - '.$t->language]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Company $company, int $templateId): ?array
    {
        $template = Template::query()
            ->where('company_id', $company->id)
            ->where('status', 'APPROVED')
            ->find($templateId);

        if (! $template) {
            return null;
        }

        return [
            'type' => 'whatsapp',
            'id' => $template->id,
            'name' => $template->name,
            'model' => $template,
        ];
    }
}
