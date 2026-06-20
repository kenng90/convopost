<?php

namespace App\Services\Messaging\Channels;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\Conversation;
use App\Services\Messaging\Contracts\MessagingChannel;
use App\Services\Messaging\DTO\ChannelCapabilities;
use App\Services\Messaging\DTO\ChannelHealth;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\DTO\SendResult;
use App\Services\Messaging\MetaMessagingParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Message;

abstract class AbstractMetaMessagingChannel implements MessagingChannel
{
    protected string $graphVersion = 'v19.0';

    public function __construct(
        protected readonly MetaMessagingParser $parser,
    ) {
    }

    abstract public function channel(): MessagingChannelType;

    public function verifyWebhook(Request $request, ChannelConnection $connection): ?Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $connection->webhook_token) {
            return response($challenge, 200);
        }

        return null;
    }

    public function parseInbound(Request $request, ChannelConnection $connection): InboundBatch
    {
        return $this->parser->parsePageMessaging($request, $this->channel());
    }

    public function send(ChannelConnection $connection, Conversation $conversation, Message $message, MessageContent $content): SendResult
    {
        $pageId = (string) $connection->credential('page_id', $connection->external_account_id);
        $token = $connection->accessToken();
        $recipientId = $conversation->external_participant_id;

        $payload = [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => $this->resolveMessagingType($conversation),
        ];

        if ($content->type === 'TEXT') {
            $payload['message'] = ['text' => $content->body];
        } elseif ($content->type === 'IMAGE' && $content->mediaUrl) {
            $payload['message'] = [
                'attachment' => [
                    'type' => 'image',
                    'payload' => ['url' => $content->mediaUrl, 'is_reusable' => true],
                ],
            ];
        } else {
            return new SendResult(false, null, __('Unsupported message type for this channel.'));
        }

        $this->applyMessagingTag($payload, $conversation);

        $response = Http::withToken($token)->post(
            "https://graph.facebook.com/{$this->graphVersion}/{$pageId}/messages",
            $payload,
        );

        if (! $response->successful()) {
            $error = data_get($response->json(), 'error.message', $response->body());

            return new SendResult(false, null, (string) $error);
        }

        return new SendResult(true, (string) data_get($response->json(), 'message_id'));
    }

    public function healthCheck(ChannelConnection $connection): ChannelHealth
    {
        if ($connection->accessToken() === '') {
            return new ChannelHealth(false, __('Access token missing.'));
        }

        return new ChannelHealth(true, $this->channel()->label().' '.__('connected.'));
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(
            text: true,
            media: true,
            templates: false,
            campaigns: false,
            flows: false,
            requiresServiceWindow: true,
            serviceWindowHours: 24,
        );
    }

    protected function resolveMessagingType(Conversation $conversation): string
    {
        if ($conversation->isWithinServiceWindow(24)) {
            return 'RESPONSE';
        }

        return 'MESSAGE_TAG';
    }

    protected function applyMessagingTag(array &$payload, Conversation $conversation): void
    {
        if (($payload['messaging_type'] ?? '') === 'MESSAGE_TAG') {
            $payload['tag'] = 'HUMAN_AGENT';
        }
    }
}
