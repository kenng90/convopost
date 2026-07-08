<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Services\HostPinnacle\HostPinnacleCredentials;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreConvoConnectCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'sub_login' => ['required', 'string', 'min:5', 'max:15', 'regex:/^[a-zA-Z0-9]+$/'],
            'user_id' => ['nullable', 'string', 'max:15', 'regex:/^[a-zA-Z0-9]+$/'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'sender_id' => ['required', 'string', 'max:11', 'regex:/^[A-Za-z0-9]+$/'],
            'sender_status' => ['required', 'in:pending_approval,approved,request_failed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Company $company */
            $company = $this->route('company');
            $provisioned = HostPinnacleCredentials::forCompany($company) !== null;

            if (! $provisioned && trim((string) $this->input('api_key', '')) === '') {
                $validator->errors()->add('api_key', __('API key is required when saving credentials for the first time.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sub_login.regex' => __('Sub-account login may only contain letters and numbers.'),
            'user_id.regex' => __('User ID may only contain letters and numbers.'),
            'sender_id.regex' => __('Sender ID may only contain letters and numbers.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function credentialsPayload(Company $company): array
    {
        $subLogin = trim((string) $this->input('sub_login'));
        $userId = trim((string) ($this->input('user_id') ?: $subLogin));
        $apiKey = trim((string) ($this->input('api_key') ?: $company->getConfig('HOSTPINNACLE_API_KEY', '')));
        $password = trim((string) ($this->input('password') ?: $company->getConfig('HOSTPINNACLE_PASSWORD', '')));

        return [
            'HOSTPINNACLE_SUB_LOGIN' => $subLogin,
            'HOSTPINNACLE_USER_ID' => $userId,
            'HOSTPINNACLE_API_KEY' => $apiKey,
            'HOSTPINNACLE_PASSWORD' => $password,
            'HOSTPINNACLE_SENDER_ID' => strtoupper(trim((string) $this->input('sender_id'))),
            'HOSTPINNACLE_SENDER_STATUS' => (string) $this->input('sender_status'),
        ];
    }
}
