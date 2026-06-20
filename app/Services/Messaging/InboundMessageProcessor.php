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
        $connection->update(['last_webhook_at' => now()]);

        $existing = Message::withoutGlobalScopes()
            ->where('fb_message_id', $inbound->externalMessageId)
            ->where('company_id', $connection->company_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = $this->conversations->findOrCreateFromInbound(
            $connection,
            $inbound->externalParticipantId,
            $inbound->participantName,
        );

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
        ]);

        $this->conversations->syncContactFromConversation(
            $conversation,
            $contact,
            $inbound->content->preview(),
            true,
        );

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
            Log::warning('Inbound notification failed', ['error' => $th->getMessage()]);
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
