<?php

namespace App\Livewire;

use App\Services\WhatsappMetaFlowService;
use App\Traits\EnsuresOpenSsl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class WhatsappFlowEncryptionSettings extends Component
{
    use EnsuresOpenSsl;

    public bool $hasKey          = false;
    public string $keyFormat     = '';
    public string $keyPreview    = '';
    public string $endpointUrl   = '';
    public bool $hasPhoneId      = false;
    public string $phoneIdHint   = '';

    public function mount(): void
    {
        $this->loadKeyStatus();
    }

    private function loadKeyStatus(): void
    {
        $company = $this->getCompany();

        if (! $company) {
            return;
        }

        // Key status
        $raw          = $company->getConfig('whatsapp_flow_private_key', '');
        $this->hasKey = ! empty(trim($raw));

        if ($this->hasKey) {
            $normalized      = $this->normalizePem($raw);
            $firstLine       = strtok($normalized, "\n");
            $this->keyFormat = str_contains($firstLine, 'BEGIN PRIVATE KEY') ? 'PKCS#8' : 'PKCS#1 (RSA)';
            $this->keyPreview = substr($normalized, 0, 27) . '...' . substr(trim($normalized), -25);
        }

        // Phone Number ID status (required for uploading to Meta)
        $phoneId           = $company->getConfig('whatsapp_phone_number_id', '');
        $this->hasPhoneId  = ! empty(trim($phoneId));
        $this->phoneIdHint = $this->hasPhoneId
            ? substr($phoneId, 0, 4) . str_repeat('*', max(0, strlen($phoneId) - 8)) . substr($phoneId, -4)
            : '';

        // Webhook endpoint URL
        $user              = auth()->user();
        $token             = $company->getConfig('plain_token', '') ?: ($user ? $user->getConfig('plain_token', '') : '');
        $this->endpointUrl = $token
            ? rtrim(config('app.url'), '/') . '/webhook/wpbox/flows/' . $token
            : '';
    }

    /**
     * Generate a new RSA keypair, store the private key, and upload the public key to Meta.
     */
    public function generateAndUploadKeys(): void
    {
        try {
            $company = $this->getCompany();

            if (! $company) {
                $this->dispatch('showNotification', type: 'error', message: 'Company not found.');
                return;
            }

            if (! $this->hasPhoneId) {
                $this->dispatch('showNotification', type: 'error', message: 'WhatsApp Phone Number ID is not set. Save it in the Facebook Developer section above first.');
                return;
            }

            $service = new WhatsappMetaFlowService();
            $keys    = $service->generateFlowEncryptionKeys($company);

            $uploadResult = $service->uploadPublicKeyToMeta($company, $keys['public_key']);

            $this->loadKeyStatus();

            if ($uploadResult['success']) {
                $this->dispatch('showNotification', type: 'success', message: 'Keys generated and uploaded to Meta successfully. All flows will now use the new keypair.');
            } else {
                $this->dispatch('showNotification', type: 'error', message: 'Key generated but upload failed: ' . $uploadResult['message'] . '. Use "Re-upload Public Key" to retry.');
            }

        } catch (\Exception $e) {
            Log::error('WhatsappFlowEncryptionSettings: generateAndUploadKeys failed', ['error' => $e->getMessage()]);
            $this->dispatch('showNotification', type: 'error', message: 'Key generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Re-derive the public key from the stored private key and re-upload to Meta.
     * Use this when keys exist locally but the upload previously failed (key mismatch / OAEP error).
     */
    public function reuploadPublicKey(): void
    {
        try {
            $company = $this->getCompany();

            if (! $company) {
                $this->dispatch('showNotification', type: 'error', message: 'Company not found.');
                return;
            }

            if (! $this->hasPhoneId) {
                $this->dispatch('showNotification', type: 'error', message: 'WhatsApp Phone Number ID is not set. Save it in the Facebook Developer section above first, then retry.');
                return;
            }

            $raw = $company->getConfig('whatsapp_flow_private_key', '');

            if (empty(trim($raw))) {
                $this->dispatch('showNotification', type: 'error', message: 'No private key stored. Click "Setup Keys" first.');
                return;
            }

            // Use loadPrivateKey() which handles both PKCS#1 and PKCS#8
            try {
                $privateKey = $this->loadPrivateKey($raw);
            } catch (\Exception $e) {
                $this->dispatch('showNotification', type: 'error', message: 'Stored private key is invalid: ' . $e->getMessage() . '. Click "Setup Keys" to regenerate.');
                return;
            }

            $details      = openssl_pkey_get_details($privateKey);
            $publicKeyPem = $details['key'] ?? null;

            if (empty($publicKeyPem)) {
                $this->dispatch('showNotification', type: 'error', message: 'Could not derive public key from stored private key. Click "Setup Keys" to regenerate.');
                return;
            }

            Log::info('WhatsappFlowEncryptionSettings: re-uploading public key to Meta', [
                'company_id'        => $company->id,
                'public_key_length' => strlen($publicKeyPem),
                'key_format'        => $this->keyFormat,
            ]);

            $service      = new WhatsappMetaFlowService();
            $uploadResult = $service->uploadPublicKeyToMeta($company, $publicKeyPem);

            if ($uploadResult['success']) {
                $this->dispatch('showNotification', type: 'success', message: 'Public key re-uploaded to Meta successfully. Trigger the health check again — it should now pass.');
            } else {
                $this->dispatch('showNotification', type: 'error', message: 'Re-upload failed: ' . $uploadResult['message']);
            }

        } catch (\Exception $e) {
            Log::error('WhatsappFlowEncryptionSettings: reuploadPublicKey failed', ['error' => $e->getMessage()]);
            $this->dispatch('showNotification', type: 'error', message: 'Re-upload failed: ' . $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.whatsapp-flow-encryption-settings');
    }

    private function getCompany(): ?\App\Models\Company
    {
        $user = auth()->user();

        return $user ? \App\Models\Company::find($user->company_id) : null;
    }
}
