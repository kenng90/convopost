<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\Contracts\MessagingChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ChannelWebhookRouter
{
    public function __construct(
        private readonly MessagingChannelRegistry $registry,
        private readonly InboundMessageProcessor $processor,
    ) {
    }

    public function handle(Request $request, string $channelSlug, string $token): Response|\Illuminate\Http\JsonResponse
    {
        Log::info('messaging.webhook.hit', [
            'channel' => $channelSlug,
            'method' => $request->method(),
            'token_prefix' => substr($token, 0, 12),
            'object' => $request->input('object'),
            'event' => $request->input('event'),
            'entry_count' => count($request->input('entry', [])),
            'query' => $request->query(),
        ]);

        $channel = MessagingChannelType::tryFromString($channelSlug);

        if ($channel === null || ! $this->registry->has($channel)) {
            Log::warning('messaging.webhook.unknown_channel', ['channel' => $channelSlug]);

            return response()->json(['error' => 'Unknown channel'], 404);
        }

        $adapter = $this->registry->get($channel);

        if ($request->isMethod('GET')) {
            return $this->verifySubscription($request, $adapter, $token);
        }

        if (! $adapter->isWebhookAuthorized($request, $token)) {
            Log::warning('messaging.webhook.invalid_token', [
                'channel' => $channel->value,
                'token_prefix' => substr($token, 0, 12),
            ]);

            return response()->json(['error' => 'Invalid token'], 403);
        }

        $connection = $adapter->resolveWebhookConnection($request, $token);

        if (! $connection) {
            Log::warning('messaging.webhook.unmatched_connection', [
                'channel' => $channel->value,
                'token_prefix' => substr($token, 0, 12),
                'payload_preview' => $this->payloadPreview($request),
            ]);

            // Acknowledge so providers do not retry unknown accounts.
            return response()->json(['ok' => true]);
        }

        return $this->processInbound($request, $connection);
    }

    private function verifySubscription(Request $request, MessagingChannel $adapter, string $urlToken): Response|\Illuminate\Http\JsonResponse
    {
        $verified = $adapter->verifyWebhook($request, $urlToken);

        Log::info('messaging.webhook.verify', [
            'channel' => $adapter->channel()->value,
            'ok' => $verified !== null,
            'url_token_prefix' => substr($urlToken, 0, 12),
        ]);

        if ($verified) {
            return $verified;
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
        if (! is_array($request->input('entry'))) {
            $content = $request->input('content');

            return [
                'event' => $request->input('event'),
                'keys' => array_keys($request->all()),
                'content_preview' => is_string($content) ? mb_substr($content, 0, 80) : null,
            ];
        }

        $entry = $request->input('entry.0', []);
        $eventSource = isset($entry['messaging'][0])
            ? 'messaging'
            : (isset($entry['standby'][0]) ? 'standby' : 'changes');
        $event = $entry['messaging'][0] ?? $entry['standby'][0] ?? data_get($entry, 'changes.0.value', []);
        if (! is_array($event)) {
            $event = [];
        }
        $message = $event['message'] ?? null;

        return [
            'object' => $request->input('object'),
            'entry_id' => $entry['id'] ?? null,
            'has_messaging' => isset($entry['messaging']),
            'messaging_count' => count($entry['messaging'] ?? []),
            'has_standby' => isset($entry['standby']),
            'standby_count' => count($entry['standby'] ?? []),
            'has_changes' => isset($entry['changes']),
            'changes_count' => count($entry['changes'] ?? []),
            'change_fields' => array_values(array_filter(array_map(
                fn ($change) => $change['field'] ?? null,
                $entry['changes'] ?? [],
            ))),
            'first_event_source' => $eventSource,
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
                'app_id' => is_array($message) ? ($message['app_id'] ?? null) : null,
                'has_text' => is_array($message) && isset($message['text']),
                'text_preview' => is_array($message) && isset($message['text'])
                    ? mb_substr((string) $message['text'], 0, 80)
                    : (is_string($message) ? mb_substr($message, 0, 80) : null),
            ],
        ];
    }
}
