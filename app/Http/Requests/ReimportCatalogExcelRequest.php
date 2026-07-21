<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReimportCatalogExcelRequest extends FormRequest
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
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'columnMapping' => ['nullable', 'json'],
            'remove_missing' => ['nullable', 'boolean'],
            'preview_only' => ['nullable', 'boolean'],
            'booking_defaults' => ['nullable', 'array'],
            'booking_defaults.default_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'booking_defaults.timezone' => ['nullable', 'string', 'max:64'],
            'booking_defaults.working_hours' => ['nullable', 'array'],
            'booking_defaults.staff_ids' => ['nullable', 'array'],
            'booking_defaults.staff_ids.*' => ['integer', 'min:1'],
            'booking_shared' => ['nullable', 'array'],
            'booking_shared.action' => ['nullable', Rule::in(['link', 'create', 'skip'])],
            'booking_shared.source_id' => ['nullable', 'integer', 'min:1'],
            'booking_shared.name' => ['nullable', 'string', 'max:255'],
            'booking_shared.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'booking_decisions' => ['nullable', 'array'],
            'booking_decisions.*.item_id' => ['required_with:booking_decisions', 'string', 'max:255'],
            'booking_decisions.*.action' => ['required_with:booking_decisions', Rule::in(['link', 'create', 'skip'])],
            'booking_decisions.*.source_id' => ['nullable', 'integer', 'min:1'],
            'booking_decisions.*.name' => ['nullable', 'string', 'max:255'],
            'booking_decisions.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select an Excel or CSV file.',
            'booking_decisions.*.action.in' => 'Each bookable row must use link, create, or skip.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('preview_only')) {
                return;
            }

            $shared = $this->input('booking_shared');
            if (is_array($shared)) {
                if (($shared['action'] ?? null) === 'link' && empty($shared['source_id'])) {
                    $validator->errors()->add(
                        'booking_shared.source_id',
                        'Choose an existing bookable service when linking.'
                    );
                }

                if (($shared['action'] ?? null) === 'create' && trim((string) ($shared['name'] ?? '')) === '') {
                    $validator->errors()->add(
                        'booking_shared.name',
                        'Enter a service name when creating a shared bookable service.'
                    );
                }
            }

            $decisions = $this->input('booking_decisions', []);
            if (! is_array($decisions)) {
                return;
            }

            foreach ($decisions as $index => $decision) {
                if (! is_array($decision)) {
                    continue;
                }

                if (($decision['action'] ?? null) === 'link' && empty($decision['source_id'])) {
                    $validator->errors()->add(
                        "booking_decisions.{$index}.source_id",
                        'Choose an existing bookable service when linking.'
                    );
                }

                if (($decision['action'] ?? null) === 'create' && trim((string) ($decision['name'] ?? '')) === '') {
                    $validator->errors()->add(
                        "booking_decisions.{$index}.name",
                        'Enter a service name when creating a bookable service.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['booking_decisions', 'booking_defaults', 'booking_shared'] as $key) {
            $value = $this->input($key);
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$key] = $decoded;
                }
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
