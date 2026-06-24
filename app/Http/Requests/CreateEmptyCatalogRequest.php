<?php

namespace App\Http\Requests;

use App\Services\Catalog\CatalogMode;
use App\Services\Catalog\CatalogTemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmptyCatalogRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'catalog_mode' => ['nullable', 'string', Rule::in(CatalogMode::all())],
            'vertical' => [
                'nullable',
                'string',
                Rule::in(array_keys($registry->verticals())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'catalog_mode.in' => 'Please choose a valid catalog type.',
            'vertical.in' => 'Please choose a valid catalog template.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('catalog_mode', CatalogMode::COMMERCE);

        if (! is_string($mode) || $mode === '') {
            $mode = CatalogMode::COMMERCE;
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
