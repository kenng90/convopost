<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'idempotency_key',
        'request_hash',
        'response_code',
        'response_body',
    ];
}
