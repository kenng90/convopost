<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contacts\Models\Contact;
use Modules\Social\Database\Factories\SocialCommentFactory;

class SocialComment extends Model
{
    use HasFactory;

    protected $table = 'social_comments';

    protected $guarded = [];

    protected $casts = [
        'raw' => 'array',
        'commented_at' => 'datetime',
    ];

    protected static function newFactory(): SocialCommentFactory
    {
        return SocialCommentFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function postAccount(): BelongsTo
    {
        return $this->belongsTo(SocialPostAccount::class, 'social_post_account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function canCreateContact(): bool
    {
        return blank($this->contact_id) && filled($this->author_external_id)
            && in_array($this->provider, ['facebook', 'instagram'], true);
    }

    public function authorLabel(): string
    {
        if (filled($this->author_name)) {
            return (string) $this->author_name;
        }

        if (filled($this->author_username)) {
            return '@'.$this->author_username;
        }

        return __('Unknown');
    }
}
