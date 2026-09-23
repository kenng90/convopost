<?php

namespace Modules\Social\Services;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\DTO\InboundMessage;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\InboundMessageProcessor;
use App\Services\Messaging\MetaCommentReply;
use App\Support\Offering;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Social\Models\SocialComment;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

/**
 * Route Social-synced Facebook/Instagram comments into the shared comments inbox.
 * Optional Flowmaker trigger rides ContactReplies (when contact bot is enabled).
 */
class SocialEngagementInboxService
{
    public function __construct(
        private readonly InboundMessageProcessor $inbound,
        private readonly SocialEngagerContactService $engagers,
    ) {
    }

    public function shouldOpenInbox(?Company $company = null): bool
    {
        if (! Offering::whatsappEnabled()) {
            return false;
        }

        if (! $company) {
            return true;
        }

        $default = (string) config('social.engagement_inbox.open_inbox_default', 'yes');

        return $company->getConfig('social_comment_open_inbox', $default) === 'yes';
    }

    public function shouldTriggerFlows(Company $company): bool
    {
        $default = (string) config('social.engagement_inbox.flow_trigger_default', 'yes');

        return $company->getConfig('social_comment_flow_trigger', $default) === 'yes';
    }

    public function shouldAutoInboxOnSync(Company $company): bool
    {
        $default = (string) config('social.engagement_inbox.auto_on_sync_default', 'no');

        return $this->shouldOpenInbox($company)
            && $company->getConfig('social_auto_inbox_on_sync', $default) === 'yes';
    }

    /**
     * Ensure contact exists, then open/update the comments-inbox conversation.
     *
     * @return array{opened: bool, skipped?: string, contact_id?: int, message_id?: int, conversation_id?: int}
     */
    public function openFromComment(SocialComment $comment, bool $createContactIfMissing = true): array
    {
        $company = Company::find($comment->company_id);

        if (! $company || ! $this->shouldOpenInbox($company)) {
            return ['opened' => false, 'skipped' => 'offering_or_config'];
        }

        $channel = $this->channelForProvider((string) $comment->provider);

        if ($channel === null) {
            return ['opened' => false, 'skipped' => 'unsupported_provider'];
        }

        $connection = $this->resolveConnection($company, $channel);

        if (! $connection) {
            return ['opened' => false, 'skipped' => 'missing_channel_connection'];
        }

        $externalId = trim((string) ($comment->author_external_id ?? ''));

        if ($externalId === '') {
            return ['opened' => false, 'skipped' => 'missing_author'];
        }

        try {
            if (! $comment->contact_id && ! $createContactIfMissing) {
                return ['opened' => false, 'skipped' => 'missing_contact'];
            }

            $contact = $this->engagers->createFromComment($comment->fresh());
        } catch (\Throwable $e) {
            Log::warning('Social engagement inbox contact failed', [
                'social_comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);

            return ['opened' => false, 'skipped' => 'contact_failed'];
        }

        if ($this->shouldTriggerFlows($company)) {
            $contact->forceFill(['enabled_ai_bot' => true])->save();
        }

        $inbound = $this->toInboundMessage($comment, $externalId);
        $message = $this->inbound->process($connection, $inbound);

        if (! $message) {
            return [
                'opened' => false,
                'skipped' => 'processor_empty',
                'contact_id' => $contact->id,
            ];
        }

        return [
            'opened' => true,
            'contact_id' => $contact->id,
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
        ];
    }

    /**
     * After Social comment sync upsert — optionally auto-open inbox for new rows.
     *
     * @return array{opened: bool, skipped?: string}
     */
    public function maybeOpenAfterSync(SocialComment $comment, bool $wasRecentlyCreated): array
    {
        if (! $wasRecentlyCreated) {
            return ['opened' => false, 'skipped' => 'not_new'];
        }

        $company = Company::find($comment->company_id);

        if (! $company || ! $this->shouldAutoInboxOnSync($company)) {
            return ['opened' => false, 'skipped' => 'auto_disabled'];
        }

        return $this->openFromComment($comment, createContactIfMissing: true);
    }

    public function findInboxMessage(SocialComment $comment): ?Message
    {
        $externalMessageId = $this->externalMessageId($comment);

        return Message::withoutGlobalScopes()
            ->where('company_id', $comment->company_id)
            ->where('fb_message_id', $externalMessageId)
            ->first();
    }

    protected function toInboundMessage(SocialComment $comment, string $externalId): InboundMessage
    {
        $body = trim((string) ($comment->body ?? ''));
        if ($body === '') {
            $body = __('(no comment text)');
        }

        $receivedAt = $comment->commented_at instanceof Carbon
            ? $comment->commented_at
            : now();

        return new InboundMessage(
            externalMessageId: $this->externalMessageId($comment),
            externalParticipantId: $externalId,
            participantName: $comment->authorLabel() !== __('Unknown')
                ? $comment->authorLabel()
                : null,
            content: MessageContent::text($body, MetaCommentReply::EXTRA_INBOUND),
            receivedAt: $receivedAt,
            raw: array_merge(is_array($comment->raw) ? $comment->raw : [], [
                'social_comment_id' => $comment->id,
                'social_post_id' => $comment->social_post_id,
            ]),
            extra: MetaCommentReply::EXTRA_INBOUND,
            context: [
                'source' => MetaCommentReply::SOURCE_COMMENT,
                'comment_id' => (string) $comment->provider_comment_id,
                'post_id' => $comment->provider_post_id,
                'social_comment_id' => $comment->id,
                'social_post_id' => $comment->social_post_id,
            ],
        );
    }

    protected function externalMessageId(SocialComment $comment): string
    {
        return 'comment:'.(string) $comment->provider_comment_id;
    }

    protected function resolveConnection(Company $company, MessagingChannelType $channel): ?ChannelConnection
    {
        return ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', $channel->value)
            ->where('status', 'connected')
            ->first();
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
