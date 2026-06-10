<?php

namespace Modules\Voicecall\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class VoicePhoneNumber extends Model
{
    protected $guarded = [];

    protected $casts = [
        'catalog_ids' => 'array',
        'handoff_phrases' => 'array',
        'required_field_keys' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $companyId = session('company_id');
            if ($companyId && empty($model->company_id)) {
                $model->company_id = $companyId;
            }
        });
    }

    public function getFlowNameAttribute(): ?string
    {
        if (! $this->voice_flow_id) {
            return null;
        }

        return \Modules\Flowmaker\Models\Flow::withoutGlobalScopes()
            ->where('id', $this->voice_flow_id)
            ->value('name');
    }
}
