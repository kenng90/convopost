<?php

namespace App\Models\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Messaging\MetaCommentReply;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'last_reply_at' => 'datetime',
        'last_client_reply_at' => 'datetime',
        'last_support_reply_at' => 'datetime',
        'is_last_message_by_contact' => 'boolean',
        'enabled_ai_bot' => 'boolean',
        'channel' => MessagingChannelType::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isWithinServiceWindow(int $hours = 24): bool
    {
        if ($this->last_client_reply_at === null) {
            return false;
        }

        return $this->last_client_reply_at->greaterThan(now()->subHours($hours));
    }

    public function commentId(): string
    {
        return (string) data_get($this->metadata, 'comment_id', '');
    }

    public function hasOpenComment(): bool
    {
        return $this->commentId() !== '';
    }

    public function isCommentOnly(): bool
    {
        return $this->hasOpenComment() && ! (bool) data_get($this->metadata, 'has_direct_message', false);
    }

    public function belongsToMessagesInbox(): bool
    {
        return ! $this->isCommentOnly();
    }

    public function belongsToCommentsInbox(): bool
    {
        return $this->hasOpenComment();
    }

    /**
     * @return list<string>
     */
    public static function inboxKindsForContact(int $contactId): array
    {
        $conversations = static::withoutGlobalScopes()
            ->where('contact_id', $contactId)
            ->get(['id', 'metadata']);

        $kinds = [];

        if ($conversations->isEmpty() || $conversations->contains(fn (self $conversation) => $conversation->belongsToMessagesInbox())) {
            $kinds[] = 'messages';
        }

        if ($conversations->contains(fn (self $conversation) => $conversation->belongsToCommentsInbox())) {
            $kinds[] = 'comments';
        }

        return $kinds;
    }

    public function scopeCommentsInbox($query)
    {
        return $query
            ->whereNotNull('metadata->comment_id')
            ->where('metadata->comment_id', '!=', '')
            ->where('metadata->comment_id', '!=', 'null');
    }

    public function scopeMessagesInbox($query)
    {
        return $query->where(function ($messages) {
            $messages
                ->whereNull('metadata->comment_id')
                ->orWhere('metadata->comment_id', '')
                ->orWhere('metadata->has_direct_message', true)
                ->orWhere('metadata->has_direct_message', 1)
                ->orWhere('metadata->has_direct_message', 'true');
        });
    }

    public function canDirectMessage(): bool
    {
        return (bool) data_get($this->metadata, 'has_direct_message', true);
    }

    public function canPrivateCommentReply(): bool
    {
        if (! $this->hasOpenComment()) {
            return false;
        }

        $alreadySentForThisComment = (bool) data_get($this->metadata, 'private_reply_sent', false)
            && (string) data_get($this->metadata, 'private_reply_comment_id', '') === $this->commentId();

        return ! $alreadySentForThisComment && $this->isWithinCommentPrivateReplyWindow();
    }

    public function isWithinCommentPrivateReplyWindow(int $days = MetaCommentReply::PRIVATE_WINDOW_DAYS): bool
    {
        $receivedAt = data_get($this->metadata, 'comment_received_at');

        if (! $receivedAt) {
            return $this->isWithinServiceWindow(24);
        }

        return Carbon::parse($receivedAt)->greaterThan(now()->subDays($days));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function commentReplyPayload(): ?array
    {
        if (! $this->hasOpenComment()) {
            return null;
        }

        $canDirect = $this->canDirectMessage();

        return [
            'comment_id' => $this->commentId(),
            'permalink' => data_get($this->metadata, 'permalink') ?: null,
            'can_public' => true,
            'can_private' => $this->canPrivateCommentReply(),
            'can_direct' => $canDirect,
            'default_mode' => $canDirect ? 'direct' : 'public',
            'comment_received_at' => data_get($this->metadata, 'comment_received_at'),
        ];
    }
}
