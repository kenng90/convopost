<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialHashtagGroupFactory;

class SocialHashtagGroup extends Model
{
    use HasFactory;

    protected $table = 'social_hashtag_groups';

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
    ];

    protected static function newFactory(): SocialHashtagGroupFactory
    {
        return SocialHashtagGroupFactory::new();
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

    public function formattedTags(): string
    {
        $tags = collect($this->tags ?? [])
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->map(function (string $tag) {
                return str_starts_with($tag, '#') ? $tag : '#'.$tag;
            })
            ->unique()
            ->values()
            ->all();

        return implode(' ', $tags);
    }
}
