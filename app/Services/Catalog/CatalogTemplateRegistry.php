<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;
use InvalidArgumentException;

class CatalogTemplateRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function modes(): array
    {
        return config('catalog-templates.modes', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function verticals(): array
    {
        return config('catalog-templates.verticals', []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function modesForApi(): array
    {
        return collect($this->modes())
            ->map(function (array $mode, string $key) {
                return array_merge($mode, [
                    'key' => $key,
                    'verticals' => $this->verticalsForMode($key),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function verticalsForMode(string $mode): array
    {
        return collect($this->verticals())
            ->filter(fn (array $vertical) => ($vertical['mode'] ?? '') === $mode)
            ->map(fn (array $vertical, string $key) => array_merge($vertical, [
                'key' => $key,
                'excel_headers' => $this->excelHeadersForVertical($key),
            ]))
            ->values()
            ->all();
    }

    public function modeExists(string $mode): bool
    {
        return array_key_exists($mode, $this->modes());
    }

    public function verticalExists(string $vertical): bool
    {
        return array_key_exists($vertical, $this->verticals());
    }

    /**
     * @return array<string, mixed>
     */
    public function mode(string $mode): array
    {
        if (! $this->modeExists($mode)) {
            throw new InvalidArgumentException("Unknown catalog mode [{$mode}].");
        }

        return $this->modes()[$mode];
    }

    /**
     * @return array<string, mixed>
     */
    public function vertical(string $vertical): array
    {
        if (! $this->verticalExists($vertical)) {
            throw new InvalidArgumentException("Unknown catalog vertical [{$vertical}].");
        }

        return $this->verticals()[$vertical];
    }

    public function verticalMatchesMode(string $vertical, string $mode): bool
    {
        return ($this->vertical($vertical)['mode'] ?? '') === $mode;
    }

    public function defaultVerticalForMode(string $mode): string
    {
        return (string) ($this->mode($mode)['default_vertical'] ?? 'retail');
    }

    public function importTemplateFilename(string $catalogMode, string $vertical): string
    {
        $modeSlug = str_replace('_', '-', $catalogMode);
        $verticalSlug = str_replace('_', '-', $vertical);

        return "catalog-import-{$modeSlug}-{$verticalSlug}.xlsx";
    }

    /**
     * @return list<string>
     */
    public function columnsForVertical(string $vertical): array
    {
        $base = ['id', 'title', 'description', 'price', 'category', 'imageUrl', 'tags'];
        $fields = $this->vertical($vertical)['item_fields'] ?? [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $base[] = $key;
            }
        }

        return array_values(array_unique($base));
    }

    /**
     * @return array<string, mixed>
     */
    public function presentationForCatalog(ListCatalog $catalog): array
    {
        $mode = $catalog->resolvedCatalogMode();
        $vertical = $catalog->resolvedVertical();
        $modeConfig = $this->mode($mode);
        $verticalConfig = $this->vertical($vertical);

        return [
            'mode' => $mode,
            'vertical' => $vertical,
            'mode_label' => $modeConfig['label'] ?? ucfirst($mode),
            'vertical_label' => $verticalConfig['label'] ?? ucfirst($vertical),
            'supports_cart' => (bool) ($modeConfig['supports_cart'] ?? false),
            'supports_checkout' => (bool) ($modeConfig['supports_checkout'] ?? false),
            'supports_inventory' => (bool) ($modeConfig['supports_inventory'] ?? false),
            'item_noun' => $modeConfig['item_noun'] ?? 'item',
            'item_noun_plural' => $modeConfig['item_noun_plural'] ?? 'items',
            'public_title_suffix' => $modeConfig['public_title_suffix'] ?? 'Catalog',
            'search_placeholder' => $modeConfig['search_placeholder'] ?? 'Search...',
            'inquire_cta_label' => $verticalConfig['inquire_cta_label']
                ?? $modeConfig['inquire_cta_label']
                ?? 'Inquire on WhatsApp',
            'book_cta_label' => $verticalConfig['book_cta_label']
                ?? $modeConfig['cta_label']
                ?? 'Book',
            'apply_cta_label' => $verticalConfig['apply_cta_label']
                ?? $verticalConfig['book_cta_label']
                ?? $modeConfig['cta_label']
                ?? 'Apply',
            'email_apply_cta_label' => $verticalConfig['email_apply_cta_label'] ?? 'Apply via email',
            'supports_booking' => array_key_exists('supports_booking', $verticalConfig)
                ? (bool) $verticalConfig['supports_booking']
                : (bool) ($modeConfig['supports_booking'] ?? false),
            'supports_email_apply' => (bool) ($verticalConfig['supports_email_apply'] ?? false),
            // Primary card CTA for listing/service is Book; commerce keeps Add to Cart.
            'cta_label' => ($modeConfig['supports_cart'] ?? false)
                ? ($modeConfig['cta_label'] ?? 'Add to Cart')
                : ($verticalConfig['apply_cta_label']
                    ?? $verticalConfig['book_cta_label']
                    ?? $modeConfig['cta_label']
                    ?? 'Book'),
            'status_field' => $verticalConfig['status_field'] ?? 'stockStatus',
            'status_options' => $verticalConfig['status_options'] ?? [],
            'card_highlights' => $verticalConfig['card_highlights'] ?? [],
            'item_fields' => $verticalConfig['item_fields'] ?? [],
            'filter_facets' => $verticalConfig['filter_facets'] ?? ['category', 'tag', 'price'],
            'supports_geo_map' => (bool) ($verticalConfig['supports_geo_map'] ?? false),
        ];
    }

    /**
     * Default API column mapping for listing/service vertical feeds.
     *
     * @return array<string, string>
     */
    public function defaultApiColumnMapping(string $vertical): array
    {
        $mapping = [
            'id' => 'id',
            'title' => 'title',
            'description' => 'description',
            'price' => 'price',
            'category' => 'category',
            'imageUrl' => 'image_url',
            'images' => 'images',
            'tags' => 'tags',
        ];

        if (! $this->verticalExists($vertical)) {
            return $mapping;
        }

        foreach ($this->vertical($vertical)['item_fields'] ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $mapping[$key] = $key;
            }
        }

        return $mapping;
    }

    /**
     * @return list<string>
     */
    public function excelHeadersForVertical(?string $vertical = null): array
    {
        if ($vertical === null || $vertical === '' || $vertical === 'retail') {
            return [
                'Item ID', 'Title', 'Description', 'Price', 'Category',
                'Image URL', 'Stock Status', 'Variants', 'Tags',
            ];
        }

        if (! $this->verticalExists($vertical)) {
            return [
                'Item ID', 'Title', 'Description', 'Price', 'Category',
                'Image URL', 'Stock Status', 'Variants', 'Tags',
            ];
        }

        $verticalConfig = $this->vertical($vertical);
        if (($verticalConfig['mode'] ?? '') === CatalogMode::COMMERCE) {
            return [
                'Item ID', 'Title', 'Description', 'Price', 'Category',
                'Image URL', 'Stock Status', 'Variants', 'Tags',
            ];
        }

        $headers = ['Item ID', 'Title', 'Description', 'Price', 'Category', 'Image URL', 'Image URLs', 'Tags'];

        foreach ($verticalConfig['item_fields'] ?? [] as $field) {
            $label = (string) ($field['label'] ?? '');
            if ($label !== '') {
                $headers[] = $label;
            }
        }

        return $headers;
    }

    /**
     * @return array<string, list<string>>
     */
    public function excelFieldAliasesForVertical(?string $vertical = null): array
    {
        $aliases = [
            'id' => ['itemid', 'id', 'sku', 'productid', 'item_id', 'product_id', 'code', 'reference'],
            'title' => ['title', 'name', 'product', 'productname', 'itemname', 'item'],
            'description' => ['description', 'desc', 'details', 'notes', 'remarks'],
            'price' => ['price', 'cost', 'amount', 'rate', 'fee', 'value'],
            'category' => ['category', 'type', 'class', 'group'],
            'imageUrl' => ['imageurl', 'image', 'imgurl', 'picture', 'photo', 'img'],
            'images' => ['imageurls', 'images', 'gallery', 'photos', 'pictures'],
            'latitude' => ['latitude', 'lat'],
            'longitude' => ['longitude', 'lng', 'lon', 'long'],
            'stockStatus' => ['stockstatus', 'stock', 'availability', 'instock'],
            'variants' => ['variants', 'variant', 'sizes', 'options', 'size'],
            'tags' => ['tags', 'tag', 'labels', 'label'],
            'listing_status' => ['listingstatus', 'status', 'availability', 'listing_status'],
            'location' => ['location', 'area', 'city', 'neighborhood', 'address'],
            'bedrooms' => ['bedrooms', 'beds', 'bedroom', 'bed'],
            'bathrooms' => ['bathrooms', 'baths', 'bathroom', 'bath'],
            'area_sqm' => ['areasqm', 'area', 'sqm', 'squaremeters', 'size'],
            'property_type' => ['propertytype', 'property', 'proptype'],
            'make' => ['make', 'brand', 'manufacturer'],
            'model' => ['model'],
            'year' => ['year', 'modelyear'],
            'mileage' => ['mileage', 'km', 'kilometers', 'odometer'],
            'fuel_type' => ['fueltype', 'fuel'],
            'duration' => ['duration', 'length'],
            'availability' => ['availability', 'available'],
            'booking_source_name' => ['bookableservice', 'bookingservice', 'remindersservice', 'bookingsourcename', 'bookingsource'],
            'booking_source_id' => ['bookableserviceid', 'bookingsourceid', 'reminderssourceid', 'serviceid'],
            'company' => ['company', 'employer', 'organization', 'organisation'],
            'employment_type' => ['employmenttype', 'employment', 'jobtype', 'worktype'],
            'salary' => ['salary', 'pay', 'compensation', 'wage', 'remuneration'],
            'experience' => ['experience', 'exp', 'years'],
            'education' => ['education', 'qualification', 'qualifications'],
            'skills' => ['skills', 'skill', 'requirements'],
            'deadline' => ['deadline', 'applicationdeadline', 'closingdate', 'applyby'],
            'benefits' => ['benefits', 'perks'],
            'apply_email' => ['applyemail', 'applytoemail', 'applicationemail', 'hr_email', 'hremail'],
        ];

        if ($vertical === null || ! $this->verticalExists($vertical)) {
            return $aliases;
        }

        foreach ($this->vertical($vertical)['item_fields'] ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || isset($aliases[$key])) {
                continue;
            }

            $normalizedLabel = strtolower(preg_replace('/[^a-z0-9]+/', '', (string) ($field['label'] ?? '')) ?? '');
            $aliases[$key] = array_values(array_unique(array_filter([
                $normalizedLabel,
                strtolower(str_replace('_', '', $key)),
                $key,
            ])));
        }

        return $aliases;
    }

    public function resolveExcelFieldFromHeader(string $header, ?string $vertical = null): ?string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', trim($header)) ?? '');

        foreach ($this->excelFieldAliasesForVertical($vertical) as $field => $fieldAliases) {
            if (in_array($normalized, $fieldAliases, true)) {
                return $field;
            }
        }

        if ($vertical !== null && $this->verticalExists($vertical)) {
            foreach ($this->vertical($vertical)['item_fields'] ?? [] as $field) {
                $label = (string) ($field['label'] ?? '');
                $key = (string) ($field['key'] ?? '');
                $labelNormalized = strtolower(preg_replace('/[^a-z0-9]+/', '', $label) ?? '');
                if ($labelNormalized !== '' && $labelNormalized === $normalized) {
                    return $key;
                }
            }
        }

        return null;
    }
}
