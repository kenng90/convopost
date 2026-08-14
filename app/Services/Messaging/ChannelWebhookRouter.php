<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ChannelWebhookRouter
{
    public function __construct(
        private readonly MessagingChannelRegistry $registry,
        private readonly InboundMessageProcessor $processor,
        private readonly MetaWebhookConnectionResolver $metaResolver,
    ) {
    }

    public function handle(Request $request, string $channelSlug, string $token): Response|\Illuminate\Http\JsonResponse
    {
        Log::info('messaging.webhook.hit', [
            'channel' => $channelSlug,
            'method' => $request->method(),
            'token_prefix' => substr($token, 0, 12),
            'object' => $request->input('object'),
            'entry_count' => count($request->input('entry', [])),
            'query' => $request->query(),
        ]);

        $channel = MessagingChannelType::tryFromString($channelSlug);

        if ($channel === null) {
            Log::warning('messaging.webhook.unknown_channel', ['channel' => $channelSlug]);

            return response()->json(['error' => 'Unknown channel'], 404);
        }

        if ($request->isMethod('GET')) {
            return $this->verifySubscription($request, $channel, $token);
        }

        if (! $this->metaResolver->isAuthorizedToken($token, $channel)) {
            Log::warning('messaging.webhook.invalid_token', [
                'channel' => $channel->value,
                'token_prefix' => substr($token, 0, 12),
            ]);

            return response()->json(['error' => 'Invalid token'], 403);
        }

        $connection = $this->metaResolver->resolve($request, $channel, $token);

        if (! $connection) {
            Log::warning('messaging.webhook.unmatched_asset', [
                'channel' => $channel->value,
                'asset_ids' => $this->metaResolver->extractAssetIds($request),
                'known_meta_accounts' => $this->knownMetaAccountSummary(),
                'payload_preview' => $this->payloadPreview($request),
            ]);

            // Acknowledge so Meta does not retry unknown Pages/IG accounts.
            return response()->json(['ok' => true]);
        }

        return $this->processInbound($request, $connection);
    }

    private function verifySubscription(Request $request, MessagingChannelType $channel, string $urlToken): Response|\Illuminate\Http\JsonResponse
    {
        $mode = $request->query('hub_mode');
        $verifyToken = (string) $request->query('hub_verify_token', '');
        $challenge = $request->query('hub_challenge');

        $ok = $mode === 'subscribe'
            && $challenge !== null
            && $verifyToken !== ''
            && $this->metaResolver->isAuthorizedToken($verifyToken, $channel);

        Log::info('messaging.webhook.verify', [
            'channel' => $channel->value,
            'ok' => $ok,
            'hub_mode' => $mode,
            'url_token_prefix' => substr($urlToken, 0, 12),
        ]);

        if ($ok) {
            return response($challenge, 200);
        }

        return response()->json([], 403);
    }

    private function processInbound(Request $request, ChannelConnection $connection): \Illuminate\Http\JsonResponse
    {
        $adapter = $this->registry->get($connection->channel);

        try {
            $batch = $adapter->parseInbound($request, $connection);

            Log::info('messaging.webhook.parsed', [
                'channel' => $connection->channel->value,
                'connection_id' => $connection->id,
                'company_id' => $connection->company_id,
                'message_count' => count($batch->messages),
                'is_status_update' => $batch->isStatusUpdate,
                'payload_preview' => $this->payloadPreview($request),
            ]);

            if ($batch->isStatusUpdate) {
                return response()->json(['ok' => true]);
            }

            if ($batch->messages === []) {
                Log::info('messaging.webhook.no_messages', [
                    'channel' => $connection->channel->value,
                    'connection_id' => $connection->id,
                    'payload_preview' => $this->payloadPreview($request),
                ]);
            }

            foreach ($batch->messages as $inbound) {
                $message = $this->processor->process($connection, $inbound);

                Log::info('messaging.webhook.message_processed', [
                    'channel' => $connection->channel->value,
                    'connection_id' => $connection->id,
                    'external_message_id' => $inbound->externalMessageId,
                    'external_participant_id' => $inbound->externalParticipantId,
                    'message_id' => $message?->id,
                    'contact_id' => $message?->contact_id,
                ]);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $th) {
            Log::error('messaging.webhook.error', [
                'channel' => $connection->channel->value,
                'connection_id' => $connection->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'payload_preview' => $this->payloadPreview($request),
            ]);

            $connection->update(['last_error' => $th->getMessage()]);

            return response()->json(['ok' => false], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadPreview(Request $request): array
    {
        $entry = $request->input('entry.0', []);
        $event = $entry['messaging'][0] ?? data_get($entry, 'changes.0.value', []);
        if (! is_array($event)) {
            $event = [];
        }
        $message = $event['message'] ?? null;

        return [
            'object' => $request->input('object'),
            'entry_id' => $entry['id'] ?? null,
            'has_messaging' => isset($entry['messaging']),
            'messaging_count' => count($entry['messaging'] ?? []),
            'has_changes' => isset($entry['changes']),
            'changes_count' => count($entry['changes'] ?? []),
            'change_fields' => array_values(array_filter(array_map(
                fn ($change) => $change['field'] ?? null,
                $entry['changes'] ?? [],
            ))),
            'first_event' => [
                'keys' => array_keys($event),
                'sender' => data_get($event, 'sender.id') ?: data_get($event, 'from.id'),
                'recipient' => data_get($event, 'recipient.id') ?: data_get($event, 'to.id'),
                'has_read' => isset($event['read']),
                'has_delivery' => isset($event['delivery']),
                'has_reaction' => isset($event['reaction']),
                'message_is_string' => is_string($message),
                'message_keys' => is_array($message) ? array_keys($message) : [],
                'is_echo' => is_array($message) ? ($message['is_echo'] ?? null) : null,
                'is_self' => is_array($message) ? ($message['is_self'] ?? null) : null,
                'has_text' => is_array($message) && isset($message['text']),
                'text_preview' => is_array($message) && isset($message['text'])
                    ? mb_substr((string) $message['text'], 0, 80)
                    : (is_string($message) ? mb_substr($message, 0, 80) : null),
            ],
        ];
    }

    /**
     * @return list<array{company_id: int, channel: string, page_id: string, instagram_account_id: string}>
     */
    private function knownMetaAccountSummary(): array
    {
        return ChannelConnection::withoutGlobalScopes()
            ->whereIn('channel', [
                MessagingChannelType::Instagram->value,
                MessagingChannelType::Messenger->value,
            ])
            ->get(['company_id', 'channel', 'external_account_id', 'credentials'])
            ->map(fn (ChannelConnection $connection) => [
                'company_id' => $connection->company_id,
                'channel' => $connection->channel->value,
                'page_id' => (string) $connection->credential('page_id', $connection->external_account_id),
                'instagram_account_id' => (string) $connection->credential('instagram_account_id', ''),
            ])
            ->values()
            ->all();
    }
}
