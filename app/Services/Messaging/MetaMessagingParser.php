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

            foreach ($entry['messaging'] ?? [] as $eventIndex => $event) {
                if (! is_array($event)) {
                    continue;
                }

                $parsed = $this->parseMessagingEvent($event, $channel, $entryIndex, $eventIndex, 'messaging', $businessIds);

                if ($parsed !== null) {
                    $messages[] = $parsed;
                }
            }

            foreach ($entry['changes'] ?? [] as $changeIndex => $change) {
                $field = $change['field'] ?? null;

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
        );
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
