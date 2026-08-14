<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MetaWebhookConnectionResolver
{
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
        $byAsset = $this->findByAssetIds($channel, $assetIds);

        if ($byAsset) {
            return $byAsset;
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

            foreach ($entry['messaging'] ?? [] as $event) {
                if (! empty($event['recipient']['id'])) {
                    $ids[] = (string) $event['recipient']['id'];
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
        $assetIds = array_values(array_unique(array_filter(array_map('strval', $assetIds))));

        if ($assetIds === []) {
            return null;
        }

        return ChannelConnection::withoutGlobalScopes()
            ->where('channel', $channel->value)
            ->where('status', 'connected')
            ->where(function ($query) use ($assetIds) {
                $query->whereIn('external_account_id', $assetIds);

                foreach ($assetIds as $assetId) {
                    $query->orWhere('credentials->page_id', $assetId)
                        ->orWhere('credentials->instagram_account_id', $assetId);
                }
            })
            ->first();
    }
}
