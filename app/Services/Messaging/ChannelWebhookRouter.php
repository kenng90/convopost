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
        $channel = MessagingChannelType::tryFromString($channelSlug);

        if ($channel === null) {
            return response()->json(['error' => 'Unknown channel'], 404);
        }

        $connection = $this->connections->findByWebhookToken($token, $channel);

        if (! $connection) {
            return response()->json(['error' => 'Invalid token'], 403);
        }

        $adapter = $this->registry->get($channel);

        if ($request->isMethod('GET')) {
            $verification = $adapter->verifyWebhook($request, $connection);

            return $verification ?? response()->json([], 403);
        }

        try {
            $batch = $adapter->parseInbound($request, $connection);

            if ($batch->isStatusUpdate) {
                return response()->json(['ok' => true]);
            }

            foreach ($batch->messages as $inbound) {
                $this->processor->process($connection, $inbound);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $th) {
            Log::error('Channel webhook error', [
                'channel' => $channel->value,
                'error' => $th->getMessage(),
            ]);

            $connection->update(['last_error' => $th->getMessage()]);

            return response()->json(['ok' => false], 500);
        }
    }
}
