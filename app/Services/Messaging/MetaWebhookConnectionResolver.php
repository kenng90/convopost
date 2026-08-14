<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Config;
use App\Models\Messaging\ChannelConnection;
use App\Services\WhatsApp\WebhookCompanyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class MetaWebhookConnectionResolver
{
    private const COMPANY_CONFIG_KEYS = [
        'instagram_account_id',
        'instagram_page_id',
        'messenger_page_id',
    ];

    public function __construct(
        private readonly ChannelConnectionService $connections,
    ) {
    }

    public function isAuthorizedToken(string $token, MessagingChannelType $channel): bool
    {
        if ($token === '') {
            return false;
        }

        if ($this->connections->findByWebhookToken($token, $channel)) {
            return true;
        }

        return PersonalAccessToken::findToken($token) !== null;
    }

    public function resolve(Request $request, MessagingChannelType $channel, string $urlToken): ?ChannelConnection
    {
        $assetIds = $this->extractAssetIds($request);
        $found = $this->findMetaConnection($channel, $assetIds);

        if ($found) {
            return $this->connectionForChannel($found, $channel, $assetIds);
        }

        $company = $this->findCompanyByConfig($assetIds);
        if ($company) {
            return $this->ensureConnectionForCompany($company, $channel, $assetIds);
        }

        return $this->connections->findByWebhookToken($urlToken, $channel);
    }

    /**
     * @return list<string>
     */
    public function extractAssetIds(Request $request): array
    {
        $ids = [];

        foreach ($request->input('entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (! empty($entry['id'])) {
                $ids[] = (string) $entry['id'];
            }

            foreach (['messaging', 'standby'] as $sourceKey) {
                foreach ($entry[$sourceKey] ?? [] as $event) {
                    if (! empty($event['recipient']['id'])) {
                        $ids[] = (string) $event['recipient']['id'];
                    }
                }
            }

            foreach ($entry['changes'] ?? [] as $change) {
                $recipientId = data_get($change, 'value.recipient.id');
                if ($recipientId) {
                    $ids[] = (string) $recipientId;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<string>  $assetIds
     */
    public function findByAssetIds(MessagingChannelType $channel, array $assetIds): ?ChannelConnection
    {
        return $this->findMetaConnection($channel, $assetIds, $channel);
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function findMetaConnection(
        MessagingChannelType $channel,
        array $assetIds,
        ?MessagingChannelType $restrictChannel = null,
    ): ?ChannelConnection {
        $assetIds = $this->normalizeAssetIds($assetIds);

        if ($assetIds === []) {
            return null;
        }

        $channels = $restrictChannel
            ? [$restrictChannel->value]
            : $this->relatedChannelValues($channel);

        $match = ChannelConnection::withoutGlobalScopes()
            ->whereIn('channel', $channels)
            ->where('status', 'connected')
            ->where(function ($query) use ($assetIds) {
                $query->whereIn('external_account_id', $assetIds);
            })
            ->first();

        if ($match) {
            return $match;
        }

        return ChannelConnection::withoutGlobalScopes()
            ->whereIn('channel', $channels)
            ->where('status', 'connected')
            ->get()
            ->first(function (ChannelConnection $connection) use ($assetIds) {
                return $this->connectionMatchesAssets($connection, $assetIds);
            });
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function findCompanyByConfig(array $assetIds): ?Company
    {
        $assetIds = $this->normalizeAssetIds($assetIds);

        if ($assetIds === []) {
            return null;
        }

        $match = Config::query()
            ->whereIn('key', self::COMPANY_CONFIG_KEYS)
            ->whereIn('value', $assetIds)
            ->where('model_type', WebhookCompanyResolver::COMPANY_MODEL)
            ->orderBy('model_id')
            ->first();

        if (! $match) {
            return null;
        }

        $company = Company::find($match->model_id);

        return $company instanceof Company ? $company : null;
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function ensureConnectionForCompany(
        Company $company,
        MessagingChannelType $channel,
        array $assetIds,
    ): ?ChannelConnection {
        $existing = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', $channel->value)
            ->where('status', 'connected')
            ->first();

        if ($existing) {
            $this->rememberInstagramAccountId($existing, $company, $assetIds);

            return $existing;
        }

        $sibling = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('channel', $this->relatedChannelValues($channel))
            ->where('status', 'connected')
            ->first();

        $pageId = '';
        $accessToken = '';
        if ($sibling) {
            $pageId = (string) $sibling->credential('page_id', $sibling->external_account_id);
            $accessToken = $sibling->accessToken();
        }

        if ($pageId === '') {
            $pageId = (string) $company->getConfig('instagram_page_id', $company->getConfig('messenger_page_id', ''));
        }

        if ($accessToken === '') {
            $accessToken = (string) $company->getConfig(
                'instagram_page_access_token',
                $company->getConfig('messenger_page_access_token', ''),
            );
        }

        if ($pageId === '') {
            $pageId = $this->guessPageId($assetIds);
        }

        if ($pageId === '' || $accessToken === '') {
            Log::warning('messaging.webhook.cannot_provision_connection', [
                'company_id' => $company->id,
                'channel' => $channel->value,
                'has_page_id' => $pageId !== '',
                'has_token' => $accessToken !== '',
            ]);

            return null;
        }

        $connection = $this->connections->upsertMetaConnection(
            $company,
            $channel,
            $pageId,
            $accessToken,
            array_filter([
                'instagram_account_id' => $this->guessInstagramAccountId($assetIds),
            ]),
        );

        $webhookToken = (string) ($sibling?->webhook_token ?: $company->getConfig('plain_token', ''));
        if ($webhookToken !== '') {
            $this->connections->storeWebhookToken($connection, $webhookToken);
        }

        Log::info('messaging.webhook.provisioned_connection', [
            'company_id' => $company->id,
            'channel' => $channel->value,
            'connection_id' => $connection->id,
            'page_id' => $pageId,
        ]);

        return $connection;
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function connectionForChannel(
        ChannelConnection $found,
        MessagingChannelType $channel,
        array $assetIds,
    ): ChannelConnection {
        $company = Company::find($found->company_id);

        if ($found->channel === $channel) {
            if ($company) {
                $this->rememberInstagramAccountId($found, $company, $assetIds);
            }

            return $found->fresh() ?? $found;
        }

        if (! $company) {
            return $found;
        }

        $provisioned = $this->ensureConnectionForCompany($company, $channel, $assetIds);

        return $provisioned ?? $found;
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function rememberInstagramAccountId(ChannelConnection $connection, Company $company, array $assetIds): void
    {
        if ($connection->channel !== MessagingChannelType::Instagram) {
            return;
        }

        $igId = $this->guessInstagramAccountId($assetIds);
        if ($igId === '' || (string) $connection->credential('instagram_account_id', '') === $igId) {
            return;
        }

        $credentials = $connection->credentials ?? [];
        $credentials['instagram_account_id'] = $igId;
        $connection->update(['credentials' => $credentials]);
        $company->setConfig('instagram_account_id', $igId);
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function connectionMatchesAssets(ChannelConnection $connection, array $assetIds): bool
    {
        $candidates = array_filter([
            (string) $connection->external_account_id,
            (string) $connection->credential('page_id', ''),
            (string) $connection->credential('instagram_account_id', ''),
        ]);

        return count(array_intersect($candidates, $assetIds)) > 0;
    }

    /**
     * @return list<string>
     */
    private function relatedChannelValues(MessagingChannelType $channel): array
    {
        return match ($channel) {
            MessagingChannelType::Instagram, MessagingChannelType::Messenger => [
                MessagingChannelType::Instagram->value,
                MessagingChannelType::Messenger->value,
            ],
            default => [$channel->value],
        };
    }

    /**
     * @param  list<string>  $assetIds
     * @return list<string>
     */
    private function normalizeAssetIds(array $assetIds): array
    {
        return array_values(array_unique(array_filter(array_map('strval', $assetIds))));
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function guessInstagramAccountId(array $assetIds): string
    {
        foreach ($this->normalizeAssetIds($assetIds) as $assetId) {
            if (str_starts_with($assetId, '178414')) {
                return $assetId;
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $assetIds
     */
    private function guessPageId(array $assetIds): string
    {
        foreach ($this->normalizeAssetIds($assetIds) as $assetId) {
            if (! str_starts_with($assetId, '178414')) {
                return $assetId;
            }
        }

        return $this->normalizeAssetIds($assetIds)[0] ?? '';
    }
}
