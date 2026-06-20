<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\InboundMessage;
use App\Services\Messaging\DTO\MessageContent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MetaMessagingParser
{
    public function parsePageMessaging(Request $request, MessagingChannelType $channel): InboundBatch
    {
        $messages = [];
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (isset($event['message']['is_echo']) && $event['message']['is_echo']) {
                    continue;
                }

                if (! isset($event['message'])) {
                    continue;
                }

                $message = $event['message'];
                $content = $this->mapMessageContent($message);

                if ($content === null) {
                    continue;
                }

                $messages[] = new InboundMessage(
                    externalMessageId: (string) $message['mid'],
                    externalParticipantId: (string) ($event['sender']['id'] ?? ''),
                    participantName: null,
                    content: $content,
                    receivedAt: Carbon::createFromTimestamp((int) floor(((int) ($event['timestamp'] ?? (time() * 1000))) / 1000)),
                    raw: $event,
                );
            }
        }

        return new InboundBatch($messages);
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
