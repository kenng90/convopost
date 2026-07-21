<?php

namespace App\Http\Requests;

use App\Services\Catalog\CatalogMode;
use App\Services\Catalog\CatalogTemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCatalogExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CatalogTemplateRegistry $registry */
        $registry = app(CatalogTemplateRegistry::class);

        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'catalog_mode' => ['nullable', 'string', Rule::in(CatalogMode::all())],
            'vertical' => [
                'nullable',
                'string',
                Rule::in(array_keys($registry->verticals())),
            ],
            'include_bookable_plan' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('catalog_mode');
        if (! is_string($mode) || $mode === '') {
            return;
        }

        /** @var CatalogTemplateRegistry $registry */
        $registry = app(CatalogTemplateRegistry::class);
        $vertical = $this->input('vertical');

        if (! is_string($vertical) || $vertical === '' || ! $registry->verticalExists($vertical)) {
            $vertical = $registry->defaultVerticalForMode($mode);
        }

        if (! $registry->verticalMatchesMode($vertical, $mode)) {
            $vertical = $registry->defaultVerticalForMode($mode);
        }

        $this->merge([
            'catalog_mode' => $mode,
            'vertical' => $vertical,
        ]);
    }
}
