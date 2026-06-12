<?php

namespace App\Http\Requests;

use App\Services\Billing\CreditActionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCreditCostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'costs' => ['required', 'array'],
            'costs.*.type' => ['required', 'in:-1,1'],
            'costs.*.cost' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $registry = app(CreditActionRegistry::class);
            $costs = $this->input('costs', []);

            foreach (array_keys($costs) as $action) {
                if (! $registry->hasAction($action)) {
                    $validator->errors()->add("costs.{$action}", __('Unknown credit action.'));
                }
            }

            foreach ($costs as $action => $values) {
                if (($values['type'] ?? '1') !== '1') {
                    continue;
                }

                if (! isset($values['cost']) || $values['cost'] === '') {
                    $validator->errors()->add("costs.{$action}.cost", __('Fixed credit amount is required.'));
                }
            }
        });
    }
}
