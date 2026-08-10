<?php

namespace App\Services\Messaging;

use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\DTO\SendResult;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class OutboundMessageService
{
    public function __construct(
        private readonly MessagingChannelRegistry $registry,
        private readonly ConversationService $conversations,
    ) {
    }

    public function send(Contact $contact, Message $message, MessageContent $content): SendResult
    {
        $channel = $contact->messagingChannel();

        try {
            $conversation = $this->conversations->ensureForContact($contact, $channel);
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
            $message->status = 5;
            $message->error = $error;
            $message->save();

            return new SendResult(false, null, $error);
        }

        $connection = $conversation->channelConnection;

        if (! $connection) {
            $error = __('Channel connection not configured.');
            $message->status = 5;
            $message->error = $error;
            $message->save();

            return new SendResult(false, null, $error);
        }

        $adapter = $this->registry->get($channel);
        $capabilities = $adapter->capabilities();

        if ($capabilities->requiresServiceWindow && ! $conversation->isWithinServiceWindow($capabilities->serviceWindowHours ?? 24)) {
            $error = __('Messaging window has expired for this channel.');
            $message->status = 5;
            $message->error = $error;
            $message->save();

            return new SendResult(false, null, $error);
        }

        $result = $adapter->send($connection, $conversation, $message, $content);

        if ($result->success && $result->externalMessageId) {
            $message->fb_message_id = $result->externalMessageId;
            $message->status = 2;
            $message->save();
        } elseif (! $result->success) {
            $message->status = 5;
            $message->error = $result->error ?? __('Send failed');
            $message->save();
        }

        $this->conversations->syncContactFromConversation(
            $conversation,
            $contact,
            $content->preview(),
            false,
        );

        return $result;
    }
}
