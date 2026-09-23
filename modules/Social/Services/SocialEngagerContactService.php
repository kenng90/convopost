<?php

namespace Modules\Social\Services;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Contacts\Models\Contact;
use Modules\Social\Models\SocialComment;

class SocialEngagerContactService
{
    /**
     * Create or link a CRM contact for a social commenter.
     * Does not open WhatsApp/inbox conversations or send messages by itself —
     * use SocialEngagementInboxService when offering mode is full.
     */
    public function createFromComment(SocialComment $comment): Contact
    {
        if ($comment->contact_id) {
            $existing = Contact::withoutGlobalScopes()->find($comment->contact_id);

            if ($existing) {
                return $existing;
            }
        }

        $externalId = trim((string) ($comment->author_external_id ?? ''));

        if ($externalId === '') {
            throw ValidationException::withMessages([
                'comment' => __('This comment has no network user id to create a contact from.'),
            ]);
        }

        $channel = $this->channelForProvider((string) $comment->provider);

        if ($channel === null) {
            throw ValidationException::withMessages([
                'comment' => __('Contacts can only be created from Facebook or Instagram engagers.'),
            ]);
        }

        return DB::transaction(function () use ($comment, $externalId, $channel) {
            $displayName = $comment->authorLabel();
            if ($displayName === __('Unknown')) {
                $displayName = $comment->author_username
                    ? '@'.$comment->author_username
                    : __('Social engager');
            }

            $identity = ChannelIdentity::withoutGlobalScopes()
                ->where('company_id', $comment->company_id)
                ->where('channel', $channel->value)
                ->where('external_id', $externalId)
                ->first();

            if ($identity) {
                $contact = Contact::withoutGlobalScopes()->find($identity->contact_id);

                if (! $contact) {
                    $contact = $this->createContact($comment->company_id, $displayName);
                    $identity->forceFill([
                        'contact_id' => $contact->id,
                        'display_name' => $displayName,
                    ])->save();
                } elseif (filled($displayName) && $displayName !== $contact->name) {
                    $contact->forceFill(['name' => $displayName])->save();
                }
            } else {
                $contact = $this->createContact($comment->company_id, $displayName);

                ChannelIdentity::withoutGlobalScopes()->create([
                    'company_id' => $comment->company_id,
                    'contact_id' => $contact->id,
                    'channel' => $channel->value,
                    'external_id' => $externalId,
                    'display_name' => $displayName,
                    'metadata' => [
                        'source' => 'social_comment',
                        'social_comment_id' => $comment->id,
                        'provider' => $comment->provider,
                    ],
                ]);
            }

            $comment->forceFill(['contact_id' => $contact->id])->save();

            // Link sibling comments from the same network user.
            SocialComment::withoutGlobalScopes()
                ->where('company_id', $comment->company_id)
                ->where('provider', $comment->provider)
                ->where('author_external_id', $externalId)
                ->whereNull('contact_id')
                ->update(['contact_id' => $contact->id]);

            return $contact;
        });
    }

    protected function createContact(int $companyId, string $name): Contact
    {
        $contact = new Contact([
            'company_id' => $companyId,
            'name' => $name,
            'phone' => '',
            'subscribed' => 1,
        ]);
        $contact->save();

        return $contact;
    }

    protected function channelForProvider(string $provider): ?MessagingChannelType
    {
        return match ($provider) {
            'facebook' => MessagingChannelType::Messenger,
            'instagram' => MessagingChannelType::Instagram,
            default => null,
        };
    }
}
