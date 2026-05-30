<?php

namespace Modules\Voicecall\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceCall extends Model
{
    protected $guarded = [];

    protected $casts = [
        'structured' => 'array',
        'handoff_requested' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function phoneNumber()
    {
        return $this->belongsTo(VoicePhoneNumber::class, 'voice_phone_number_id');
    }

    public function contact()
    {
        return $this->belongsTo(\Modules\Wpbox\Models\Contact::class, 'contact_id');
    }
}
