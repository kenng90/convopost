<?php

namespace Modules\Tiktok\Messaging;

use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\InboundMessage;
use App\Services\Messaging\DTO\MessageContent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TiktokWebhookParser
{
    private const RECEIVE_EVENT = 'im_receive_msg';

    private const STATUS_EVENTS = [
        'im_send_msg',
        'im_mark_read_msg',
    ];

    public function parse(Request $request): InboundBatch
    {
        $event = (string) $request->input('event', '');

        if (in_array($event, self::STATUS_EVENTS, true)) {
            return new InboundBatch(isStatusUpdate: true);
        }

        if ($event !== self::RECEIVE_EVENT) {
            return new InboundBatch;
        }

        $content = $this->decodeContent($request->input('content'));
        $messageId = (string) ($content['message_id'] ?? '');
        $conversationId = (string) ($content['conversation_id'] ?? '');
        $senderId = (string) ($content['sender'] ?? '');

        if ($messageId === '' || $conversationId === '' || $senderId === '') {
            return new InboundBatch;
        }

        $receivedAt = $this->timestampToCarbon(
            $content['timestamp'] ?? $request->input('create_time')
        );

        return new InboundBatch([
            new InboundMessage(
                externalMessageId: $messageId,
                externalParticipantId: $senderId,
                participantName: isset($content['sender_nickname']) ? (string) $content['sender_nickname'] : null,
                content: $this->messageContent($content),
                receivedAt: $receivedAt,
                raw: $request->all(),
                extra: null,
                context: [
                    'external_thread_id' => $conversationId,
                    'business_id' => (string) $request->input('user_openid', ''),
                ],
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeContent(mixed $content): array
    {
        if (is_array($content)) {
            return $content;
        }

        if (! is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function messageContent(array $content): MessageContent
    {
        $type = strtoupper((string) ($content['message_type'] ?? 'TEXT'));

        if ($type === 'IMAGE') {
            $url = (string) (
                data_get($content, 'image.url')
                ?: data_get($content, 'image.media_url')
                ?: data_get($content, 'media_url')
                ?: ''
            );

            if ($url !== '') {
                return MessageContent::image($url);
            }
        }

        $body = (string) (
            data_get($content, 'text.body')
            ?: data_get($content, 'text')
            ?: ''
        );

        if ($body === '' && $type !== 'TEXT') {
            $body = '['.$type.']';
        }

        return MessageContent::text($body);
    }

    private function timestampToCarbon(mixed $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            $value = (int) $timestamp;

            if ($value > 99_999_999_999) {
                $value = (int) floor($value / 1000);
            }

            return Carbon::createFromTimestamp($value);
        }

        return now();
    }
}
