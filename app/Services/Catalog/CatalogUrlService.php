<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;
use Illuminate\Support\Str;

class CatalogUrlService
{
    public function assignSlug(ListCatalog $catalog, ?string $preferred = null): string
    {
        $base = Str::slug($preferred ?: $catalog->name);
        if ($base === '') {
            $base = 'catalog';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->slugExistsForCompany($catalog->company_id, $slug, $catalog->id)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function publicUrl(ListCatalog $catalog, ?Company $company = null, array $query = []): string
    {
        $company = $company ?: $catalog->company;

        if ($company && $company->subdomain && $catalog->slug) {
            return route('catalog.shop', [
                'subdomain' => $company->subdomain,
                'slug' => $catalog->slug,
            ] + $query);
        }

        return route('catalog.public', ['catalogId' => $catalog->id] + $query);
    }

    public function resolveCatalog(string $subdomain, string $slug): ?ListCatalog
    {
        $company = Company::where('subdomain', $subdomain)->first();
        if (! $company) {
            return null;
        }

        return ListCatalog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->whereNull('parent_id')
            ->first();
    }

    private function slugExistsForCompany(int $companyId, string $slug, ?int $ignoreId = null): bool
    {
        $query = ListCatalog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
