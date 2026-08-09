<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\InboundMessage;
use App\Services\Messaging\DTO\MessageContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaMessagingParser
{
    public function parsePageMessaging(Request $request, MessagingChannelType $channel): InboundBatch
    {
        $messages = [];
        $entries = $request->input('entry', []);

        foreach ($entries as $entryIndex => $entry) {
            foreach ($entry['messaging'] ?? [] as $eventIndex => $event) {
                $parsed = $this->parseMessagingEvent($event, $channel, $entryIndex, $eventIndex, 'messaging');

                if ($parsed !== null) {
                    $messages[] = $parsed;
                }
            }

            // Instagram Graph webhooks often use entry[].changes[] instead of messaging[].
            foreach ($entry['changes'] ?? [] as $changeIndex => $change) {
                $field = $change['field'] ?? null;

                if (! in_array($field, ['messages', 'message_reactions', 'messaging'], true)) {
                    Log::debug('messaging.parser.skip_change_field', [
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

                // Some payloads nest the event under value.message / value directly.
                $event = isset($value['sender']) || isset($value['message'])
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

                $parsed = $this->parseMessagingEvent($event, $channel, $entryIndex, $changeIndex, 'changes:'.$field);

                if ($parsed !== null) {
                    $messages[] = $parsed;
                }
            }
        }

        return new InboundBatch($messages);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function parseMessagingEvent(
        array $event,
        MessagingChannelType $channel,
        int $entryIndex,
        int $eventIndex,
        string $source,
    ): ?InboundMessage {
        if (isset($event['message']['is_echo']) && $event['message']['is_echo']) {
            Log::debug('messaging.parser.skip_echo', [
                'channel' => $channel->value,
                'source' => $source,
                'entry_index' => $entryIndex,
                'event_index' => $eventIndex,
            ]);

            return null;
        }

        if (! isset($event['message'])) {
            Log::debug('messaging.parser.skip_non_message_event', [
                'channel' => $channel->value,
                'source' => $source,
                'event_keys' => array_keys($event),
            ]);

            return null;
        }

        $message = $event['message'];
        $content = $this->mapMessageContent($message);

        if ($content === null) {
            Log::info('messaging.parser.unsupported_content', [
                'channel' => $channel->value,
                'source' => $source,
                'message_keys' => array_keys($message),
                'mid' => $message['mid'] ?? null,
            ]);

            return null;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');

        if ($senderId === '') {
            Log::warning('messaging.parser.missing_sender', [
                'channel' => $channel->value,
                'source' => $source,
                'mid' => $message['mid'] ?? null,
            ]);

            return null;
        }

        return new InboundMessage(
            externalMessageId: (string) ($message['mid'] ?? ''),
            externalParticipantId: $senderId,
            participantName: null,
            content: $content,
            receivedAt: Carbon::createFromTimestamp((int) floor(((int) ($event['timestamp'] ?? (time() * 1000))) / 1000)),
            raw: $event,
        );
    }

    private function mapMessageContent(array $message): ?MessageContent
    {
        if (isset($message['text'])) {
            return MessageContent::text((string) $message['text']);
        }

        if (isset($message['attachments'][0])) {
            $attachment = $message['attachments'][0];
            $type = strtoupper((string) ($attachment['type'] ?? 'image'));
            $url = (string) ($attachment['payload']['url'] ?? '');

            if ($url === '') {
                return null;
            }

            return new MessageContent($type, $url, $url);
        }

        return null;
    }
}
