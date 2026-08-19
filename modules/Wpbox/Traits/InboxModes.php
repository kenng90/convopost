<?php

namespace Modules\Wpbox\Traits;

use App\Enums\MessagingChannelType;
use App\Services\Messaging\MetaCommentReply;
use Modules\Wpbox\Models\Contact;

trait InboxModes
{
    protected function resolveInboxMode(mixed $mode): string
    {
        return $mode === 'comments' ? 'comments' : 'messages';
    }

    protected function applyInboxModeFilter($query, string $mode): void
    {
        if ($mode === 'comments') {
            $query->whereHas('conversations', function ($conversations) {
                $conversations->withoutGlobalScopes()->commentsInbox();
            });

            return;
        }

        $query->where(function ($messagesQuery) {
            $messagesQuery
                ->whereDoesntHave('conversations')
                ->orWhereHas('conversations', function ($conversations) {
                    $conversations->withoutGlobalScopes()->messagesInbox();
                });
        });
    }

    protected function countInboxMode($query, string $mode, bool $unreadOnly = false): int
    {
        $countQuery = clone $query;
        $this->applyInboxModeFilter($countQuery, $mode);

        if ($unreadOnly) {
            $countQuery->where('is_last_message_by_contact', 1)->where('resolved_chat', 0);
        }

        return (int) $countQuery->count();
    }

    protected function presentInboxContact(Contact $contact): Contact
    {
        $contact->channel = $contact->channelIdentities->first()?->channel?->value
            ?? MessagingChannelType::Whatsapp->value;

        $conversation = $contact->conversations->first(function ($item) use ($contact) {
            $itemChannel = $item->channel instanceof MessagingChannelType
                ? $item->channel->value
                : (string) $item->channel;

            return $itemChannel === $contact->channel;
        }) ?? $contact->conversations->first();

        $contact->comment_reply = $conversation?->commentReplyPayload();
        $contact->unsetRelation('conversations');

        return $contact;
    }

    /**
     * Comment-only threads must not be sent as DMs (commenter IDs are not PSIDs).
     */
    protected function outboundCommentReplyExtra(Contact $contact, mixed $replyMode): ?string
    {
        $extra = MetaCommentReply::requestModeToExtra(is_string($replyMode) ? $replyMode : null);
        if ($extra) {
            return $extra;
        }

        if (! $contact->relationLoaded('conversations')) {
            $contact->load(['conversations' => function ($query) {
                $query->withoutGlobalScopes()
                    ->select(['id', 'contact_id', 'channel', 'metadata', 'last_client_reply_at'])
                    ->latest('id');
            }]);
        }

        $conversation = $contact->conversations->first();

        if ($conversation && $conversation->isCommentOnly()) {
            return MetaCommentReply::EXTRA_PUBLIC;
        }

        return null;
    }
}
