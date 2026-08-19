<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\InboundMessage;
use App\Services\Messaging\DTO\MessageContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaMessagingParser
{
    public function parsePageMessaging(
        Request $request,
        MessagingChannelType $channel,
        ?ChannelConnection $connection = null,
    ): InboundBatch {
        $messages = [];
        $entries = $request->input('entry', []);
        $connectionBusinessIds = $this->businessIdsFromConnection($connection);

        foreach ($entries as $entryIndex => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $businessIds = array_values(array_unique(array_merge(
                $connectionBusinessIds,
                $this->businessIdsFromEntry($entry),
            )));

            // Messenger Platform delivers customer DMs on `messaging` when this app
            // owns the thread, and on `standby` when Meta Business Suite / Page Inbox does.
            foreach (['messaging', 'standby'] as $sourceKey) {
                foreach ($entry[$sourceKey] ?? [] as $eventIndex => $event) {
                    if (! is_array($event)) {
                        continue;
                    }

                    $parsed = $this->parseMessagingEvent(
                        $event,
                        $channel,
                        $entryIndex,
                        $eventIndex,
                        $sourceKey,
                        $businessIds,
                    );

                    if ($parsed !== null) {
                        $messages[] = $parsed;
                    }
                }
            }

            foreach ($entry['changes'] ?? [] as $changeIndex => $change) {
                $field = $change['field'] ?? null;

                if (in_array($field, ['feed', 'comments', 'live_comments'], true)) {
                    $parsed = $this->parseCommentChange(
                        is_array($change) ? $change : [],
                        $channel,
                        $entryIndex,
                        $changeIndex,
                        $businessIds,
                        $entry,
                    );

                    if ($parsed !== null) {
                        $messages[] = $parsed;
                    }

                    continue;
                }

                if (! in_array($field, ['messages', 'message_reactions', 'messaging'], true)) {
                    Log::info('messaging.parser.skip_change_field', [
                        'channel' => $channel->value,
                        'field' => $field,
                        'entry_index' => $entryIndex,
                        'change_index' => $changeIndex,
                    ]);

                    continue;
                }

                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $event = isset($value['sender']) || isset($value['from']) || isset($value['message']) || isset($value['postback'])
                    ? $value
                    : null;

                if ($event === null) {
                    Log::info('messaging.parser.unrecognized_change_value', [
                        'channel' => $channel->value,
                        'field' => $field,
                        'value_keys' => array_keys($value),
                    ]);

                    continue;
                }

                $parsed = $this->parseMessagingEvent(
                    $event,
                    $channel,
                    $entryIndex,
                    $changeIndex,
                    'changes:'.$field,
                    $businessIds,
                );

                if ($parsed !== null) {
                    $messages[] = $parsed;
                }
            }
        }

        return new InboundBatch($messages);
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  list<string>  $businessIds
     */
    private function parseMessagingEvent(
        array $event,
        MessagingChannelType $channel,
        int $entryIndex,
        int $eventIndex,
        string $source,
        array $businessIds = [],
    ): ?InboundMessage {
        $senderId = $this->eventPartyId($event, 'sender') ?: $this->eventPartyId($event, 'from');
        $recipientId = $this->eventPartyId($event, 'recipient') ?: $this->eventPartyId($event, 'to');
        $message = $event['message'] ?? null;
        $fromBusiness = $senderId !== '' && in_array($senderId, $businessIds, true);

        if ($fromBusiness) {
            Log::info('messaging.parser.skip_echo', [
                'channel' => $channel->value,
                'source' => $source,
                'sender' => $senderId,
                'recipient' => $recipientId,
                'is_echo' => is_array($message) ? ($message['is_echo'] ?? null) : null,
            ]);

            return null;
        }

        if ($senderId === '') {
            Log::warning('messaging.parser.missing_sender', [
                'channel' => $channel->value,
                'source' => $source,
                'event_keys' => array_keys($event),
            ]);

            return null;
        }

        $receivedAt = Carbon::createFromTimestamp((int) floor(((int) ($event['timestamp'] ?? (time() * 1000))) / 1000));

        if (isset($event['postback']) && is_array($event['postback'])) {
            $postback = $event['postback'];
            $title = (string) ($postback['title'] ?? $postback['payload'] ?? '');
            $payload = (string) ($postback['payload'] ?? '');
            $mid = (string) ($postback['mid'] ?? (is_array($message) ? ($message['mid'] ?? '') : ''));

            if ($payload === '' && $title === '') {
                return null;
            }

            return new InboundMessage(
                externalMessageId: $mid !== '' ? $mid : 'postback:'.md5($senderId.'|'.$payload.'|'.$receivedAt->timestamp),
                externalParticipantId: $senderId,
                participantName: null,
                content: MessageContent::text($title !== '' ? $title : $payload, $payload),
                receivedAt: $receivedAt,
                raw: $event,
                extra: $payload !== '' ? $payload : null,
                context: ['source' => 'message'],
            );
        }

        if (is_string($message) && trim($message) !== '') {
            return new InboundMessage(
                externalMessageId: (string) ($event['mid'] ?? md5($senderId.'|'.$message.'|'.$receivedAt->timestamp)),
                externalParticipantId: $senderId,
                participantName: null,
                content: MessageContent::text($message),
                receivedAt: $receivedAt,
                raw: $event,
                extra: null,
                context: ['source' => 'message'],
            );
        }

        if (! is_array($message)) {
            Log::info('messaging.parser.skip_non_message_event', [
                'channel' => $channel->value,
                'source' => $source,
                'event_keys' => array_keys($event),
                'sender' => $senderId,
                'recipient' => $recipientId,
            ]);

            return null;
        }

        $content = $this->mapMessageContent($message);

        if ($content === null) {
            Log::info('messaging.parser.unsupported_content', [
                'channel' => $channel->value,
                'source' => $source,
                'message_keys' => array_keys($message),
                'mid' => $message['mid'] ?? null,
                'is_deleted' => $message['is_deleted'] ?? null,
                'is_unsupported' => $message['is_unsupported'] ?? null,
            ]);

            return null;
        }

        $extra = $content->extra;
        if (isset($message['quick_reply']['payload'])) {
            $extra = (string) $message['quick_reply']['payload'];
        }

        return new InboundMessage(
            externalMessageId: (string) ($message['mid'] ?? ''),
            externalParticipantId: $senderId,
            participantName: null,
            content: $content,
            receivedAt: $receivedAt,
            raw: $event,
            extra: $extra,
            context: ['source' => 'message'],
        );
    }

    /**
     * @param  array<string, mixed>  $change
     * @param  list<string>  $businessIds
     * @param  array<string, mixed>  $entry
     */
    private function parseCommentChange(
        array $change,
        MessagingChannelType $channel,
        int $entryIndex,
        int $changeIndex,
        array $businessIds,
        array $entry,
    ): ?InboundMessage {
        $field = (string) ($change['field'] ?? '');
        $value = $change['value'] ?? null;

        if (! is_array($value)) {
            return null;
        }

        if ($field === 'feed') {
            $item = (string) ($value['item'] ?? '');
            $verb = (string) ($value['verb'] ?? 'add');

            if ($item !== 'comment' || $verb !== 'add') {
                Log::info('messaging.parser.skip_feed_item', [
                    'channel' => $channel->value,
                    'item' => $item,
                    'verb' => $verb,
                    'entry_index' => $entryIndex,
                    'change_index' => $changeIndex,
                ]);

                return null;
            }
        }

        if (! empty($value['hidden']) || ! empty($value['is_hidden'])) {
            return null;
        }

        $senderId = $this->eventPartyId($value, 'from') ?: $this->eventPartyId($value, 'sender');

        if ($senderId === '' || in_array($senderId, $businessIds, true)) {
            Log::info('messaging.parser.skip_comment', [
                'channel' => $channel->value,
                'field' => $field,
                'reason' => $senderId === '' ? 'missing_sender' : 'echo',
                'sender' => $senderId,
            ]);

            return null;
        }

        $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
        if ($commentId === '') {
            Log::info('messaging.parser.skip_comment', [
                'channel' => $channel->value,
                'field' => $field,
                'reason' => 'missing_comment_id',
                'value_keys' => array_keys($value),
            ]);

            return null;
        }

        $text = trim((string) ($value['message'] ?? $value['text'] ?? ''));
        if ($text === '') {
            $text = __('Photo comment');
        }

        $postId = (string) ($value['post_id'] ?? data_get($value, 'post.id', ''));
        $mediaId = (string) data_get($value, 'media.id', '');
        $parentId = (string) ($value['parent_id'] ?? '');

        if ($parentId !== '' && ($parentId === $postId || $parentId === $mediaId)) {
            $parentId = '';
        }

        $participantName = data_get($value, 'from.name')
            ?: data_get($value, 'from.username')
            ?: null;

        $receivedAt = $this->timestampToCarbon(
            $value['created_time'] ?? $value['timestamp'] ?? $entry['time'] ?? time()
        );

        return new InboundMessage(
            externalMessageId: 'comment:'.$commentId,
            externalParticipantId: $senderId,
            participantName: is_string($participantName) && $participantName !== '' ? $participantName : null,
            content: MessageContent::text($text),
            receivedAt: $receivedAt,
            raw: $value,
            extra: MetaCommentReply::EXTRA_INBOUND,
            context: [
                'source' => MetaCommentReply::SOURCE_COMMENT,
                'comment_id' => $commentId,
                'parent_comment_id' => $parentId !== '' ? $parentId : null,
                'post_id' => $postId !== '' ? $postId : null,
                'media_id' => $mediaId !== '' ? $mediaId : null,
                'permalink' => (string) (data_get($value, 'post.permalink_url') ?: data_get($value, 'permalink_url') ?: ''),
                'field' => $field,
            ],
        );
    }

    private function timestampToCarbon(mixed $timestamp): Carbon
    {
        $value = (int) $timestamp;

        if ($value > 9999999999) {
            $value = (int) floor($value / 1000);
        }

        if ($value <= 0) {
            return now();
        }

        return Carbon::createFromTimestamp($value);
    }

    private function mapMessageContent(array $message): ?MessageContent
    {
        if (! empty($message['is_deleted']) || ! empty($message['is_unsupported'])) {
            return null;
        }

        if (isset($message['text']) && $message['text'] !== '') {
            $extra = isset($message['quick_reply']['payload'])
                ? (string) $message['quick_reply']['payload']
                : null;

            return MessageContent::text((string) $message['text'], $extra);
        }

        if (isset($message['quick_reply']['payload'])) {
            $payload = (string) $message['quick_reply']['payload'];

            return MessageContent::text($payload, $payload);
        }

        if (isset($message['attachments'][0]) && is_array($message['attachments'][0])) {
            $attachment = $message['attachments'][0];
            $type = strtoupper((string) ($attachment['type'] ?? 'image'));
            $url = (string) ($attachment['payload']['url'] ?? $attachment['payload']['reel_video_id'] ?? '');

            if ($url !== '') {
                $mappedType = in_array($type, ['IMAGE', 'VIDEO', 'AUDIO', 'FILE', 'SHARE'], true) ? $type : 'IMAGE';

                return new MessageContent($mappedType === 'FILE' ? 'DOCUMENT' : $mappedType, $url, $url);
            }

            if (in_array(strtolower((string) ($attachment['type'] ?? '')), ['like_heart', 'sticker'], true)) {
                return MessageContent::text('❤️');
            }

            $title = (string) ($attachment['payload']['title'] ?? $attachment['title'] ?? '');
            if ($title !== '') {
                return MessageContent::text($title);
            }

            return MessageContent::text('['.strtolower((string) ($attachment['type'] ?? 'attachment')).']');
        }

        if (isset($message['reply_to']['story'])) {
            return MessageContent::text(__('Story reply'));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function businessIdsFromEntry(array $entry): array
    {
        $ids = [];

        if (! empty($entry['id'])) {
            $ids[] = (string) $entry['id'];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return list<string>
     */
    private function businessIdsFromConnection(?ChannelConnection $connection): array
    {
        if ($connection === null) {
            return [];
        }

        return array_values(array_unique(array_filter([
            (string) $connection->external_account_id,
            (string) $connection->credential('page_id', ''),
            (string) $connection->credential('instagram_account_id', ''),
        ])));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventPartyId(array $event, string $key): string
    {
        $party = $event[$key] ?? null;

        if (is_string($party) && $party !== '') {
            return $party;
        }

        if (is_array($party) && ! empty($party['id'])) {
            return (string) $party['id'];
        }

        return '';
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
