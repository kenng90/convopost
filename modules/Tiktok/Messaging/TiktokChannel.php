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
        $platformToken = (string) config('services.tiktok.webhook_token', '');
        if ($platformToken !== ''
            && strlen($platformToken) === strlen($urlToken)
            && hash_equals($platformToken, $urlToken)) {
            return true;
        }

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
        $businessId = (string) $connection->credential('business_id', $connection->external_account_id);
        $token = $connection->accessToken();

        if ($token === '' || $businessId === '') {
            return new SendResult(false, null, __('TikTok credentials are incomplete. Reconnect the channel.'));
        }

        if ($content->isPublicCommentReply()) {
            return $this->sendPublicCommentReply($conversation, $content, $token, $businessId);
        }

        if ($content->isPrivateCommentReply()) {
            return $this->sendPrivateCommentReply($conversation, $content, $token, $businessId);
        }

        $conversationId = trim((string) $conversation->external_thread_id);

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
            flows: true,
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

    private function sendPublicCommentReply(
        Conversation $conversation,
        MessageContent $content,
        string $token,
        string $businessId,
    ): SendResult {
        $commentId = $conversation->commentId();
        $videoId = (string) data_get(
            $conversation->metadata,
            'media_id',
            data_get($conversation->metadata, 'post_id', '')
        );

        if ($commentId === '' || $videoId === '') {
            return new SendResult(false, null, __('No comment is linked to this conversation.'));
        }

        if ($content->type !== 'TEXT' || trim($content->body) === '') {
            return new SendResult(false, null, __('Public comment replies must be text.'));
        }

        $result = $this->client->replyToPublicComment(
            $token,
            $businessId,
            $videoId,
            $commentId,
            $content->body,
        );

        if (! $result['ok']) {
            return new SendResult(false, null, $result['message'] !== '' ? $result['message'] : __('TikTok public comment reply failed.'));
        }

        $externalId = (string) data_get($result['data'], 'comment_id', data_get($result['data'], 'reply_id', ''));

        return new SendResult(true, $externalId !== '' ? $externalId : null);
    }

    private function sendPrivateCommentReply(
        Conversation $conversation,
        MessageContent $content,
        string $token,
        string $businessId,
    ): SendResult {
        $commentId = $conversation->commentId();

        if ($commentId === '') {
            return new SendResult(false, null, __('No comment is linked to this conversation.'));
        }

        if (! $conversation->canPrivateCommentReply()) {
            if (! (bool) data_get($conversation->metadata, 'high_intent', false)) {
                return new SendResult(false, null, __('Private replies are only available for high-intent TikTok comments.'));
            }

            return new SendResult(false, null, __('A private reply was already sent for this comment, or the reply window has expired.'));
        }

        if ($content->type !== 'TEXT' || trim($content->body) === '') {
            return new SendResult(false, null, __('Private comment replies must be text.'));
        }

        $result = $this->client->sendDirectReply(
            $token,
            $businessId,
            $commentId,
            $content->body,
        );

        if (! $result['ok']) {
            return new SendResult(false, null, $result['message'] !== '' ? $result['message'] : __('TikTok Comment-to-Message reply failed.'));
        }

        $conversation->forceFill([
            'metadata' => array_merge($conversation->metadata ?? [], [
                'private_reply_sent' => true,
                'private_reply_comment_id' => $commentId,
            ]),
        ])->save();

        $externalId = (string) data_get($result['data'], 'message.message_id', data_get($result['data'], 'message_id', ''));

        return new SendResult(true, $externalId !== '' ? $externalId : null);
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
