<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use Modules\Wpbox\Models\Contact;

class ConversationService
{
    public function __construct(
        private readonly ChannelConnectionService $connections,
    ) {
    }

    public function ensureForContact(Contact $contact, MessagingChannelType $channel): Conversation
    {
        $company = Company::findOrFail($contact->company_id);
        $connection = $this->resolveConnection($company, $channel);
        $externalId = $this->resolveExternalParticipantId($contact, $channel);

        $identity = ChannelIdentity::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $company->id,
                'channel' => $channel->value,
                'external_id' => $externalId,
            ],
            [
                'contact_id' => $contact->id,
                'display_name' => $contact->name,
                'avatar_url' => $contact->avatar,
            ],
        );

        if ($identity->contact_id !== $contact->id) {
            $identity->update(['contact_id' => $contact->id]);
        }

        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
            [
                'channel_connection_id' => $connection?->id,
                'external_participant_id' => $externalId,
            ],
            [
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'channel' => $channel->value,
                'assigned_user_id' => $contact->user_id,
                'enabled_ai_bot' => (bool) $contact->enabled_ai_bot,
                'status' => $contact->resolved_chat ? 'resolved' : 'open',
            ],
        );

        if ($conversation->contact_id !== $contact->id) {
            $conversation->update(['contact_id' => $contact->id]);
        }

        return $conversation;
    }

    public function findOrCreateFromInbound(
        ChannelConnection $connection,
        string $externalParticipantId,
        ?string $participantName = null,
        ?string $avatarUrl = null,
    ): Conversation {
        $channel = $connection->channel;
        $companyId = $connection->company_id;

        $conversation = Conversation::withoutGlobalScopes()
            ->where('channel_connection_id', $connection->id)
            ->where('external_participant_id', $externalParticipantId)
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $contact = $this->findOrCreateContact(
            $companyId,
            $channel,
            $externalParticipantId,
            $participantName,
            $avatarUrl,
        );

        return Conversation::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => $channel->value,
            'external_participant_id' => $externalParticipantId,
            'assigned_user_id' => $contact->user_id,
            'enabled_ai_bot' => true,
            'status' => 'open',
            'last_reply_at' => now(),
        ]);
    }

    public function syncContactFromConversation(Conversation $conversation, Contact $contact, string $preview, bool $fromContact): void
    {
        $conversation->update([
            'contact_id' => $contact->id,
            'last_message' => mb_substr($preview, 0, 40),
            'last_reply_at' => now(),
            'last_client_reply_at' => $fromContact ? now() : $conversation->last_client_reply_at,
            'last_support_reply_at' => $fromContact ? $conversation->last_support_reply_at : now(),
            'is_last_message_by_contact' => $fromContact,
            'status' => $fromContact ? 'open' : $conversation->status,
        ]);

        $contact->has_chat = true;
        $contact->last_message = mb_substr($preview, 0, 40);
        $contact->last_reply_at = now();

        if ($fromContact) {
            $contact->last_client_reply_at = now();
            $contact->is_last_message_by_contact = true;
            $contact->resolved_chat = 0;
        } else {
            $contact->last_support_reply_at = now();
            $contact->is_last_message_by_contact = false;
        }

        $contact->save();
    }

    private function resolveConnection(Company $company, MessagingChannelType $channel): ?ChannelConnection
    {
        if ($channel === MessagingChannelType::Whatsapp) {
            if ($company->getConfig('whatsapp_permanent_access_token', '') === '') {
                return null;
            }

            return $this->connections->ensureWhatsappConnection($company);
        }

        return ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', $channel->value)
            ->where('status', 'connected')
            ->first();
    }

    private function resolveExternalParticipantId(Contact $contact, MessagingChannelType $channel): string
    {
        $identity = ChannelIdentity::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('channel', $channel->value)
            ->first();

        if ($identity) {
            return $identity->external_id;
        }

        return match ($channel) {
            MessagingChannelType::Whatsapp => (string) $contact->phone,
            MessagingChannelType::Instagram, MessagingChannelType::Messenger => 'meta:'.$contact->id,
            default => (string) $contact->id,
        };
    }

    private function findOrCreateContact(
        int $companyId,
        MessagingChannelType $channel,
        string $externalParticipantId,
        ?string $participantName,
        ?string $avatarUrl,
    ): Contact {
        $identity = ChannelIdentity::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('channel', $channel->value)
            ->where('external_id', $externalParticipantId)
            ->first();

        if ($identity) {
            return Contact::withoutGlobalScopes()->findOrFail($identity->contact_id);
        }

        $phone = match ($channel) {
            MessagingChannelType::Whatsapp => $externalParticipantId,
            default => '',
        };

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => $participantName ?: __('Unknown'),
            'phone' => $phone,
            'avatar' => $avatarUrl ?: '',
            'company_id' => $companyId,
            'has_chat' => true,
            'enabled_ai_bot' => $channel === MessagingChannelType::Whatsapp,
            'subscribed' => 1,
            'last_reply_at' => now(),
            'is_last_message_by_contact' => true,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'contact_id' => $contact->id,
            'channel' => $channel->value,
            'external_id' => $externalParticipantId,
            'display_name' => $participantName ?: '',
            'avatar_url' => $avatarUrl,
        ]);

        return $contact;
    }
}
