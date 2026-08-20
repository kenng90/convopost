<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\DTO\InboundMessage;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Events\ContactReplies;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Notifications\ContactReplies as NotificationContactReplies;

class InboundMessageProcessor
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessagingChannelRegistry $registry,
    ) {
    }

    public function process(ChannelConnection $connection, InboundMessage $inbound): ?Message
    {
        $connection->update(['last_webhook_at' => now(), 'last_error' => null]);

        $existing = Message::withoutGlobalScopes()
            ->where('fb_message_id', $inbound->externalMessageId)
            ->where('company_id', $connection->company_id)
            ->first();

        if ($existing) {
            Log::info('messaging.inbound.duplicate', [
                'channel' => $connection->channel->value,
                'company_id' => $connection->company_id,
                'external_message_id' => $inbound->externalMessageId,
                'message_id' => $existing->id,
            ]);

            return $existing;
        }

        $conversation = $this->conversations->findOrCreateFromInbound(
            $connection,
            $inbound->externalParticipantId,
            $inbound->participantName,
        );

        $this->conversations->applyInboundContext($conversation, $inbound);

        $contact = Contact::withoutGlobalScopes()->findOrFail($conversation->contact_id);
        $messageType = $inbound->content->type;
        $content = $inbound->content->body;

        $message = Message::withoutGlobalScopes()->create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'company_id' => $connection->company_id,
            'channel' => $connection->channel->value,
            'value' => $messageType === 'TEXT' ? $content : '',
            'header_image' => $messageType === 'IMAGE' ? $content : '',
            'header_video' => $messageType === 'VIDEO' ? $content : '',
            'header_audio' => $messageType === 'AUDIO' ? $content : '',
            'header_document' => $messageType === 'DOCUMENT' ? $content : '',
            'is_message_by_contact' => true,
            'is_campign_messages' => false,
            'status' => 1,
            'buttons' => '[]',
            'components' => '',
            'fb_message_id' => $inbound->externalMessageId,
            'extra' => $inbound->extra ?? $inbound->content->extra ?? '',
        ]);

        // Ensure chat list shows this contact (some Message creates omit contact flags).
        $preview = $inbound->isComment()
            ? __('Comment: :text', ['text' => $inbound->content->preview()])
            : $inbound->content->preview();

        $this->conversations->syncContactFromConversation(
            $conversation,
            $contact,
            $preview,
            true,
        );

        Log::info('messaging.inbound.created', [
            'channel' => $connection->channel->value,
            'company_id' => $connection->company_id,
            'connection_id' => $connection->id,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'message_id' => $message->id,
            'external_message_id' => $inbound->externalMessageId,
            'external_participant_id' => $inbound->externalParticipantId,
            'type' => $messageType,
            'has_chat' => (bool) $contact->fresh()->has_chat,
        ]);

        if ($contact->enabled_ai_bot && $connection->channel === MessagingChannelType::Whatsapp) {
            $contact->botReply($content, $message);
        }

        try {
            $company = Company::findOrFail($connection->company_id);
            $companyUser = $company->user;

            if ($companyUser) {
                event(new ContactReplies($companyUser, $message, $contact));
                $companyUser->notify(new NotificationContactReplies($companyUser, $message, $contact));
            }
        } catch (\Throwable $th) {
            Log::warning('messaging.inbound.notification_failed', [
                'error' => $th->getMessage(),
                'message_id' => $message->id,
            ]);
        }

        event(new Chatlistchange($contact->id, $connection->company_id));

        return $message;
    }

    public function attachConversationToMessage(Message $message, Contact $contact): Message
    {
        if ($message->conversation_id) {
            return $message;
        }

        $channel = MessagingChannelType::tryFromString($message->channel)
            ?? $contact->messagingChannel();

        $conversation = $this->conversations->ensureForContact($contact, $channel);

        $message->conversation_id = $conversation->id;
        $message->channel = $channel->value;
        $message->save();

        return $message;
    }
}
