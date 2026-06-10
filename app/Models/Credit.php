<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credit extends Model
{
    use SoftDeletes;

    protected $table = 'credit';

    protected $fillable = [
        'company_id',
        'user_id',
        'credit_amount',
        'used_credit_amount',
        'remaining_credit_amount',
        'expiration_date',
        'source',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
