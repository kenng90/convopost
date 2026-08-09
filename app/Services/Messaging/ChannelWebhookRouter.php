<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ChannelWebhookRouter
{
    public function __construct(
        private readonly ChannelConnectionService $connections,
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
            'entry_count' => count($request->input('entry', [])),
            'query' => $request->query(),
        ]);

        $channel = MessagingChannelType::tryFromString($channelSlug);

        if ($channel === null) {
            Log::warning('messaging.webhook.unknown_channel', ['channel' => $channelSlug]);

            return response()->json(['error' => 'Unknown channel'], 404);
        }

        $connection = $this->connections->findByWebhookToken($token, $channel);

        if (! $connection) {
            Log::warning('messaging.webhook.invalid_token', [
                'channel' => $channel->value,
                'token_prefix' => substr($token, 0, 12),
            ]);

            return response()->json(['error' => 'Invalid token'], 403);
        }

        $adapter = $this->registry->get($channel);

        if ($request->isMethod('GET')) {
            $verification = $adapter->verifyWebhook($request, $connection);

            Log::info('messaging.webhook.verify', [
                'channel' => $channel->value,
                'connection_id' => $connection->id,
                'ok' => $verification !== null,
                'hub_mode' => $request->query('hub_mode'),
            ]);

            return $verification ?? response()->json([], 403);
        }

        try {
            $batch = $adapter->parseInbound($request, $connection);

            Log::info('messaging.webhook.parsed', [
                'channel' => $channel->value,
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
                    'channel' => $channel->value,
                    'connection_id' => $connection->id,
                    'payload_preview' => $this->payloadPreview($request),
                ]);
            }

            foreach ($batch->messages as $inbound) {
                $message = $this->processor->process($connection, $inbound);

                Log::info('messaging.webhook.message_processed', [
                    'channel' => $channel->value,
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
                'channel' => $channel->value,
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
        ];
    }
}
