<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use Laravel\Sanctum\PersonalAccessToken;

class ChannelConnectionService
{
    public function findByWebhookToken(string $token, MessagingChannelType $channel): ?ChannelConnection
    {
        return ChannelConnection::withoutGlobalScopes()
            ->where('channel', $channel->value)
            ->where('webhook_token', $token)
            ->where('status', 'connected')
            ->first();
    }

    public function isAuthorizedWebhookToken(string $token, MessagingChannelType $channel): bool
    {
        if ($token === '') {
            return false;
        }

        if ($this->findByWebhookToken($token, $channel)) {
            return true;
        }

        return PersonalAccessToken::findToken($token) !== null;
    }

    public function findByExternalAccount(
        MessagingChannelType $channel,
        string $externalAccountId,
    ): ?ChannelConnection {
        return ChannelConnection::withoutGlobalScopes()
            ->where('channel', $channel->value)
            ->where('external_account_id', $externalAccountId)
            ->where('status', 'connected')
            ->first();
    }

    public function ensureWhatsappConnection(Company $company): ChannelConnection
    {
        $wabaId = (string) $company->getConfig('whatsapp_business_account_id', '');
        $phoneId = (string) $company->getConfig('whatsapp_phone_number_id', '');
        $token = (string) $company->getConfig('whatsapp_permanent_access_token', '');

        $connection = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Whatsapp->value)
            ->when($wabaId !== '', fn ($q) => $q->where('external_account_id', $wabaId))
            ->first();

        $credentials = [
            'access_token' => $token,
            'phone_number_id' => $phoneId,
            'waba_id' => $wabaId,
            'facebook_app_id' => $company->getConfig('facebook_app_id', ''),
        ];

        $payload = [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Whatsapp->value,
            'external_account_id' => $wabaId !== '' ? $wabaId : null,
            'display_name' => 'WhatsApp',
            'status' => $token !== '' && $phoneId !== '' ? 'connected' : 'pending',
            'credentials' => $credentials,
            'capabilities' => ['text', 'image', 'video', 'audio', 'document', 'template', 'campaigns', 'flows'],
        ];

        if ($connection) {
            $connection->update($payload);

            return $connection->fresh();
        }

        return ChannelConnection::withoutGlobalScopes()->create($payload);
    }

    public function upsertMetaConnection(
        Company $company,
        MessagingChannelType $channel,
        string $pageId,
        string $accessToken,
        array $extra = [],
    ): ChannelConnection {
        $credentials = array_merge([
            'access_token' => $accessToken,
            'page_id' => $pageId,
        ], $extra);

        $connection = ChannelConnection::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'channel' => $channel->value,
                'external_account_id' => $pageId,
            ],
            [
                'display_name' => $channel->label(),
                'status' => 'connected',
                'credentials' => $credentials,
                'capabilities' => ['text', 'image'],
            ],
        );

        $configKey = match ($channel) {
            MessagingChannelType::Instagram => 'instagram_connected',
            MessagingChannelType::Messenger => 'messenger_connected',
            default => null,
        };

        if ($configKey) {
            $company->setConfig($configKey, 'yes');
        }

        return $connection;
    }

    public function storeWebhookToken(ChannelConnection $connection, string $plainToken): void
    {
        $connection->update([
            'webhook_token' => $plainToken,
        ]);
    }
}
