<?php

namespace Modules\Whatsappcall\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'structured' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'handoff_requested' => 'boolean',
    ];

    public function answeredBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'answered_by');
    }

    public function resolveWaCallId(): ?string
    {
        if ($this->wa_call_id) {
            return $this->wa_call_id;
        }

        $meta = $this->meta ?? [];

        return data_get($meta, 'calls.0.id')
            ?? data_get($meta, 'calls.0.call_id')
            ?? data_get($meta, 'call.id');
    }
}
