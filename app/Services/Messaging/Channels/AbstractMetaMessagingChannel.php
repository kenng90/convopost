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
        $actorId = $this->resolveGraphActorId($connection);
        $token = $connection->accessToken();
        $recipientId = trim((string) $conversation->external_participant_id);

        if ($actorId === '') {
            return new SendResult(false, null, __('Facebook Page ID is required to send replies. Update channel setup.'));
        }

        if ($recipientId === '' || str_starts_with($recipientId, 'meta:')) {
            return new SendResult(false, null, __('Missing Instagram/Messenger user id for this conversation.'));
        }

        // Guard against replying to our own Page/IG business id (wrong webhook party stored).
        $selfIds = array_filter([
            (string) $connection->credential('page_id', $connection->external_account_id),
            (string) $connection->credential('instagram_account_id', ''),
            (string) $connection->external_account_id,
        ]);

        if (in_array($recipientId, $selfIds, true)) {
            return new SendResult(
                false,
                null,
                __('Invalid recipient id (looks like your Page/Instagram business id). Ask the customer to message again so we can capture their user id.'),
            );
        }

        $payload = [
            'recipient' => ['id' => $recipientId],
        ];

        // Messenger requires messaging_type. Instagram Messaging API examples often omit it for in-window replies.
        if ($this->channel() === MessagingChannelType::Messenger || ! $conversation->isWithinServiceWindow(24)) {
            $payload['messaging_type'] = $this->resolveMessagingType($conversation);
            $this->applyMessagingTag($payload, $conversation);
        }

        if ($content->type === 'TEXT') {
            $payload['message'] = ['text' => $content->body];

            if (! empty($content->quickReplies)) {
                $payload['message']['quick_replies'] = array_values(array_map(function (array $reply) {
                    return [
                        'content_type' => $reply['content_type'] ?? 'text',
                        'title' => mb_substr((string) ($reply['title'] ?? ''), 0, 20),
                        'payload' => (string) ($reply['payload'] ?? ''),
                    ];
                }, $content->quickReplies));
            }
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

        $url = "https://graph.facebook.com/{$this->graphVersion}/{$actorId}/messages";

        $response = Http::withToken($token)->asJson()->post($url, $payload);

        // Some Page tokens behave more reliably against /me/messages than /{page-id}/messages.
        if (
            ! $response->successful()
            && $this->channel() === MessagingChannelType::Instagram
            && $actorId !== 'me'
            && (int) data_get($response->json(), 'error.code') === 100
        ) {
            $fallback = Http::withToken($token)->asJson()->post(
                "https://graph.facebook.com/{$this->graphVersion}/me/messages",
                $payload,
            );

            if ($fallback->successful()) {
                return new SendResult(true, (string) data_get($fallback->json(), 'message_id'));
            }

            \Illuminate\Support\Facades\Log::warning('messaging.outbound.failed', [
                'channel' => $this->channel()->value,
                'actor_id' => 'me',
                'recipient_id' => $recipientId,
                'status' => $fallback->status(),
                'error' => data_get($fallback->json(), 'error.message'),
                'error_code' => data_get($fallback->json(), 'error.code'),
                'error_subcode' => data_get($fallback->json(), 'error.error_subcode'),
                'fallback' => true,
            ]);

            $response = $fallback;
        }

        if (! $response->successful()) {
            $error = data_get($response->json(), 'error.message', $response->body());
            $subcode = data_get($response->json(), 'error.error_subcode');

            \Illuminate\Support\Facades\Log::warning('messaging.outbound.failed', [
                'channel' => $this->channel()->value,
                'actor_id' => $actorId,
                'recipient_id' => $recipientId,
                'status' => $response->status(),
                'error' => $error,
                'error_code' => data_get($response->json(), 'error.code'),
                'error_subcode' => $subcode,
            ]);

            return new SendResult(false, null, $this->humanizeGraphError((string) $error, $subcode));
        }

        return new SendResult(true, (string) data_get($response->json(), 'message_id'));
    }

    /**
     * Messenger API for Instagram uses the linked Facebook Page id (or "me") with a Page token.
     * Instagram Login messaging uses graph.instagram.com/{IG_ID}/messages — not this path.
     */
    protected function resolveGraphActorId(ChannelConnection $connection): string
    {
        $pageId = (string) $connection->credential('page_id', $connection->external_account_id);

        // Prefer explicit Page id; "me" also works with a Page access token.
        return $pageId !== '' ? $pageId : 'me';
    }

    protected function humanizeGraphError(string $error, mixed $subcode = null): string
    {
        if ((int) $subcode === 2018001 || str_contains($error, 'No matching user found')) {
            return __('(#100) No matching user found. Usually the Facebook Page is not linked to the Instagram account that received the DM, or the Page token is missing instagram_manage_messages. Re-save Instagram setup after linking Page↔IG and regenerating the token.');
        }

        if ((int) $subcode === 2534013) {
            return __('This Facebook Page is not linked to an Instagram Professional account.');
        }

        if (str_contains($error, 'does not support this operation') || str_contains($error, 'missing permissions')) {
            return $error.' '.__('Use a Facebook Page access token with instagram_manage_messages + pages_messaging. Do not POST to the Instagram business account id on graph.facebook.com.');
        }

        return $error;
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
            flows: true,
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
