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
        return $this->parser->parsePageMessaging($request, $this->channel(), $connection);
    }

    public function send(ChannelConnection $connection, Conversation $conversation, Message $message, MessageContent $content): SendResult
    {
        if ($content->isPublicCommentReply()) {
            return $this->sendPublicCommentReply($connection, $conversation, $content);
        }

        if ($content->isPrivateCommentReply()) {
            return $this->sendPrivateCommentReply($connection, $conversation, $content);
        }

        $actorId = $this->resolveGraphActorId($connection);
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

        $token = $this->resolvePageAccessToken($connection);

        if ($token === '') {
            return new SendResult(false, null, __('Page access token is missing. Reconnect Messenger/Instagram with a Facebook Page token.'));
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

        $graph = "https://graph.facebook.com/{$this->graphVersion}";
        $endpoints = array_values(array_unique([
            $graph.'/me/messages',
            $graph.'/'.$actorId.'/messages',
        ]));

        $lastResponse = null;

        foreach ($endpoints as $url) {
            $response = Http::withToken($token)->asJson()->post($url, $payload);

            if ($response->successful()) {
                return new SendResult(true, (string) data_get($response->json(), 'message_id'));
            }

            $lastResponse = $response;

            \Illuminate\Support\Facades\Log::warning('messaging.outbound.failed', [
                'channel' => $this->channel()->value,
                'actor_id' => str_contains($url, '/me/messages') ? 'me' : $actorId,
                'recipient_id' => $recipientId,
                'status' => $response->status(),
                'error' => data_get($response->json(), 'error.message'),
                'error_code' => data_get($response->json(), 'error.code'),
                'error_subcode' => data_get($response->json(), 'error.error_subcode'),
                'fbtrace_id' => data_get($response->json(), 'error.fbtrace_id'),
                'endpoint' => $url,
            ]);
        }

        $error = data_get($lastResponse?->json(), 'error.message', $lastResponse?->body() ?? '');
        $subcode = data_get($lastResponse?->json(), 'error.error_subcode');
        $code = data_get($lastResponse?->json(), 'error.code');

        return new SendResult(false, null, $this->humanizeGraphError((string) $error, $subcode, $code));
    }

    /**
     * Messenger/Instagram Send API requires a Page access token. Embedded Signup often
     * stores a WhatsApp business token instead; exchange it via /me/accounts when possible.
     */
    protected function resolvePageAccessToken(ChannelConnection $connection): string
    {
        $token = $connection->accessToken();
        $pageId = (string) $connection->credential('page_id', $connection->external_account_id);

        if ($token === '' || $pageId === '') {
            return $token;
        }

        if ($this->isMarkedAsPageToken($connection)) {
            return $token;
        }

        $graph = "https://graph.facebook.com/{$this->graphVersion}";
        $me = Http::withToken($token)->get($graph.'/me', ['fields' => 'id']);
        $meId = (string) data_get($me->json(), 'id', '');

        if ($me->successful() && $meId === $pageId) {
            $this->persistPageToken($connection, $token);

            return $token;
        }

        \Illuminate\Support\Facades\Log::info('messaging.outbound.token_not_page', [
            'channel' => $this->channel()->value,
            'page_id' => $pageId,
            'me_id' => $meId !== '' ? $meId : null,
            'status' => $me->status(),
        ]);

        $accounts = Http::withToken($token)->get($graph.'/me/accounts', [
            'fields' => 'id,access_token',
        ]);

        foreach ($accounts->json('data') ?? [] as $account) {
            if (! is_array($account)) {
                continue;
            }

            if ((string) ($account['id'] ?? '') !== $pageId) {
                continue;
            }

            $pageToken = (string) ($account['access_token'] ?? '');
            if ($pageToken === '') {
                continue;
            }

            $this->persistPageToken($connection, $pageToken);

            \Illuminate\Support\Facades\Log::info('messaging.outbound.exchanged_page_token', [
                'channel' => $this->channel()->value,
                'page_id' => $pageId,
            ]);

            return $pageToken;
        }

        return $token;
    }

    protected function isMarkedAsPageToken(ChannelConnection $connection): bool
    {
        $flag = $connection->credential('token_is_page', false);

        return $flag === true || $flag === 1 || $flag === '1';
    }

    protected function persistPageToken(ChannelConnection $connection, string $pageToken): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['access_token'] = $pageToken;
        $credentials['token_is_page'] = true;
        $connection->credentials = $credentials;
        $connection->update(['credentials' => $credentials]);

        $company = $connection->company;
        if ($company) {
            $company->setConfig('instagram_page_access_token', $pageToken);
            $company->setConfig('messenger_page_access_token', $pageToken);
        }
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

    protected function humanizeGraphError(string $error, mixed $subcode = null, mixed $code = null): string
    {
        if ((int) $code === 1 || str_contains($error, 'An unknown error has occurred')) {
            return __('Meta rejected the send (error 1). The stored token is usually a WhatsApp Embedded Signup token, not a Facebook Page access token, or pages_messaging is not approved. In Graph API Explorer choose User or Page → your Page, copy that Page token, paste it in Messenger/Instagram setup, then retry.');
        }

        if ((int) $subcode === 2018001 || str_contains($error, 'No matching user found')) {
            return __('(#100) No matching user found. Usually the Facebook Page is not linked to the Instagram account that received the DM, or the Page token is missing instagram_manage_messages. Re-save Instagram setup after linking Page↔IG and regenerating the token.');
        }

        if ((int) $subcode === 2534013) {
            return __('This Facebook Page is not linked to an Instagram Professional account.');
        }

        if (str_contains($error, 'private reply') || str_contains($error, 'already been used')) {
            return __('A private reply was already sent for this comment. Reply publicly on the post, or wait for the customer to message you.');
        }

        if ((int) $code === 10 || str_contains($error, 'instagram_manage_comments') || str_contains($error, 'pages_manage_engagement')) {
            return $error.' '.__('Comment replies need pages_manage_engagement (Facebook) or instagram_manage_comments (Instagram) on the Page token, plus App Review for live apps.');
        }

        if (str_contains($error, 'does not support this operation') || str_contains($error, 'missing permissions')) {
            return $error.' '.__('Use a Facebook Page access token with instagram_manage_messages + pages_messaging. Do not POST to the Instagram business account id on graph.facebook.com.');
        }

        return $error;
    }

    protected function sendPublicCommentReply(ChannelConnection $connection, Conversation $conversation, MessageContent $content): SendResult
    {
        $commentId = $conversation->commentId();

        if ($commentId === '') {
            return new SendResult(false, null, __('No Facebook/Instagram comment is linked to this conversation.'));
        }

        if ($content->type !== 'TEXT' || trim($content->body) === '') {
            return new SendResult(false, null, __('Public comment replies must be text.'));
        }

        $token = $this->resolvePageAccessToken($connection);

        if ($token === '') {
            return new SendResult(false, null, __('Page access token is missing. Reconnect Messenger/Instagram with a Facebook Page token.'));
        }

        $endpoint = $this->channel() === MessagingChannelType::Instagram ? 'replies' : 'comments';
        $url = "https://graph.facebook.com/{$this->graphVersion}/{$commentId}/{$endpoint}";
        $response = Http::withToken($token)->asJson()->post($url, [
            'message' => $content->body,
        ]);

        if ($response->successful()) {
            $externalId = (string) (data_get($response->json(), 'id') ?: data_get($response->json(), 'message_id') ?: '');

            return new SendResult(true, $externalId !== '' ? $externalId : 'comment-reply:'.$commentId);
        }

        \Illuminate\Support\Facades\Log::warning('messaging.comment.public_failed', [
            'channel' => $this->channel()->value,
            'comment_id' => $commentId,
            'status' => $response->status(),
            'error' => data_get($response->json(), 'error.message'),
            'error_code' => data_get($response->json(), 'error.code'),
        ]);

        return new SendResult(
            false,
            null,
            $this->humanizeGraphError(
                (string) data_get($response->json(), 'error.message', $response->body()),
                data_get($response->json(), 'error.error_subcode'),
                data_get($response->json(), 'error.code'),
            ),
        );
    }

    protected function sendPrivateCommentReply(ChannelConnection $connection, Conversation $conversation, MessageContent $content): SendResult
    {
        $commentId = $conversation->commentId();

        if ($commentId === '') {
            return new SendResult(false, null, __('No Facebook/Instagram comment is linked to this conversation.'));
        }

        if ($content->type !== 'TEXT' || trim($content->body) === '') {
            return new SendResult(false, null, __('Private comment replies must be text.'));
        }

        $token = $this->resolvePageAccessToken($connection);

        if ($token === '') {
            return new SendResult(false, null, __('Page access token is missing. Reconnect Messenger/Instagram with a Facebook Page token.'));
        }

        $payload = [
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => $content->body],
        ];

        $graph = "https://graph.facebook.com/{$this->graphVersion}";
        $actorId = $this->resolveGraphActorId($connection);
        $endpoints = array_values(array_unique([
            $graph.'/me/messages',
            $graph.'/'.$actorId.'/messages',
        ]));

        $lastResponse = null;

        foreach ($endpoints as $url) {
            $response = Http::withToken($token)->asJson()->post($url, $payload);

            if ($response->successful()) {
                $conversation->forceFill([
                    'metadata' => array_merge($conversation->metadata ?? [], [
                        'private_reply_sent' => true,
                        'private_reply_comment_id' => $commentId,
                    ]),
                ])->save();

                return new SendResult(true, (string) data_get($response->json(), 'message_id'));
            }

            $lastResponse = $response;

            \Illuminate\Support\Facades\Log::warning('messaging.comment.private_failed', [
                'channel' => $this->channel()->value,
                'comment_id' => $commentId,
                'status' => $response->status(),
                'error' => data_get($response->json(), 'error.message'),
                'error_code' => data_get($response->json(), 'error.code'),
                'endpoint' => $url,
            ]);
        }

        $error = data_get($lastResponse?->json(), 'error.message', $lastResponse?->body() ?? '');
        $subcode = data_get($lastResponse?->json(), 'error.error_subcode');
        $code = data_get($lastResponse?->json(), 'error.code');

        return new SendResult(false, null, $this->humanizeGraphError((string) $error, $subcode, $code));
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
