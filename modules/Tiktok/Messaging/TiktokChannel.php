<?php

namespace Modules\Tiktok\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\Conversation;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\Messaging\Contracts\MessagingChannel;
use App\Services\Messaging\DTO\ChannelCapabilities;
use App\Services\Messaging\DTO\ChannelHealth;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\DTO\SendResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Wpbox\Models\Message;

class TiktokChannel implements MessagingChannel
{
    public const MAX_OUTBOUND_IN_WINDOW = 10;

    public function __construct(
        private readonly ChannelConnectionService $connections,
        private readonly TiktokWebhookParser $parser,
        private readonly TiktokClient $client,
    ) {
    }

    public function channel(): MessagingChannelType
    {
        return MessagingChannelType::Tiktok;
    }

    public function verifyWebhook(Request $request, string $urlToken): ?Response
    {
        if (! $this->isWebhookAuthorized($request, $urlToken)) {
            return null;
        }

        $challenge = $request->query('challenge', $request->query('echostr'));
        if ($challenge !== null && $challenge !== '') {
            return response((string) $challenge, 200);
        }

        return response('ok', 200);
    }

    public function isWebhookAuthorized(Request $request, string $urlToken): bool
    {
        return $this->connections->isAuthorizedWebhookToken($urlToken, $this->channel());
    }

    public function resolveWebhookConnection(Request $request, string $urlToken): ?ChannelConnection
    {
        $businessId = (string) $request->input('user_openid', '');

        if ($businessId !== '') {
            $matched = $this->connections->findByExternalAccount($this->channel(), $businessId);
            if ($matched) {
                return $matched;
            }
        }

        return $this->connections->findByWebhookToken($urlToken, $this->channel());
    }

    public function parseInbound(Request $request, ChannelConnection $connection): InboundBatch
    {
        return $this->parser->parse($request);
    }

    public function send(ChannelConnection $connection, Conversation $conversation, Message $message, MessageContent $content): SendResult
    {
        $conversationId = trim((string) $conversation->external_thread_id);
        $businessId = (string) $connection->credential('business_id', $connection->external_account_id);
        $token = $connection->accessToken();

        if ($token === '' || $businessId === '') {
            return new SendResult(false, null, __('TikTok credentials are incomplete. Reconnect the channel.'));
        }

        if ($conversationId === '') {
            return new SendResult(false, null, __('Missing TikTok conversation id for this thread. Wait for the customer to message again.'));
        }

        if ($this->hasReachedOutboundCap($conversation, $message)) {
            return new SendResult(
                false,
                null,
                __('TikTok allows 10 replies within 48 hours of the customer’s last message. Wait for them to reply again.'),
            );
        }

        $payload = $this->outboundPayload($content);
        if ($payload === null) {
            return new SendResult(false, null, __('Unsupported message type for TikTok.'));
        }

        $result = $this->client->sendMessage(
            $token,
            $businessId,
            $conversationId,
            $payload['message_type'],
            $payload['body'],
        );

        if (! $result['ok']) {
            return new SendResult(false, null, $result['message'] !== '' ? $result['message'] : __('TikTok send failed.'));
        }

        $externalId = (string) data_get($result['data'], 'message.message_id', data_get($result['data'], 'message_id', ''));

        return new SendResult(true, $externalId !== '' ? $externalId : null);
    }

    public function healthCheck(ChannelConnection $connection): ChannelHealth
    {
        $token = $connection->accessToken();
        $businessId = (string) $connection->credential('business_id', $connection->external_account_id);

        if ($token === '' || $businessId === '') {
            return new ChannelHealth(false, __('TikTok credentials incomplete.'));
        }

        $result = $this->client->getCapabilities($token, $businessId);

        if (! $result['ok']) {
            return new ChannelHealth(false, $result['message'] !== '' ? $result['message'] : __('TikTok health check failed.'));
        }

        return new ChannelHealth(true, __('TikTok connected.'));
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
            serviceWindowHours: 48,
        );
    }

    private function hasReachedOutboundCap(Conversation $conversation, Message $message): bool
    {
        if ($conversation->last_client_reply_at === null) {
            return true;
        }

        $alreadySent = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('is_message_by_contact', false)
            ->where('id', '!=', $message->id)
            ->where('created_at', '>=', $conversation->last_client_reply_at)
            ->count();

        return $alreadySent >= self::MAX_OUTBOUND_IN_WINDOW;
    }

    /**
     * @return array{message_type: string, body: array<string, mixed>}|null
     */
    private function outboundPayload(MessageContent $content): ?array
    {
        if ($content->type === 'TEXT') {
            return [
                'message_type' => 'TEXT',
                'body' => [
                    'text' => ['body' => $content->body],
                ],
            ];
        }

        if ($content->type === 'IMAGE' && $content->mediaUrl) {
            return [
                'message_type' => 'IMAGE',
                'body' => [
                    'image' => ['url' => $content->mediaUrl],
                ],
            ];
        }

        return null;
    }
}
