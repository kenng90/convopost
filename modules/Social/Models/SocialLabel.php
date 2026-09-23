<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialLabelFactory;

class SocialLabel extends Model
{
    use HasFactory;

    protected $table = 'social_labels';

    protected $guarded = [];

    protected static function newFactory(): SocialLabelFactory
    {
        return SocialLabelFactory::new();
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
}
