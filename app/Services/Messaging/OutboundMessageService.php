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
        $replyMode = MetaCommentReply::extraToMode((string) $message->extra);

        if ($replyMode === null && $conversation->hasOpenComment() && ! $conversation->canDirectMessage()) {
            $replyMode = MetaCommentReply::MODE_PUBLIC;
            $message->extra = MetaCommentReply::EXTRA_PUBLIC;
            $message->save();
        }

        $content = $content->withReplyMode($replyMode);

        if ($content->isPublicCommentReply()) {
            if (! $conversation->hasOpenComment()) {
                $error = __('No Facebook/Instagram comment is linked to this conversation.');
                $message->status = 5;
                $message->error = $error;
                $message->save();

                return new SendResult(false, null, $error);
            }
        } elseif ($content->isPrivateCommentReply()) {
            if (! $conversation->hasOpenComment()) {
                $error = __('No Facebook/Instagram comment is linked to this conversation.');
                $message->status = 5;
                $message->error = $error;
                $message->save();

                return new SendResult(false, null, $error);
            }

            if (! $conversation->isWithinCommentPrivateReplyWindow()) {
                $error = __('The 7-day private reply window for this comment has expired.');
                $message->status = 5;
                $message->error = $error;
                $message->save();

                return new SendResult(false, null, $error);
            }
        } elseif ($capabilities->requiresServiceWindow && ! $conversation->isWithinServiceWindow($capabilities->serviceWindowHours ?? 24)) {
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
