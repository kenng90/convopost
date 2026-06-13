<?php

namespace App\Models;

use App\Services\Billing\CreditCostService;
use Illuminate\Database\Eloquent\Model;

class Cost extends Model
{
    protected $modelName = 'App\Models\Cost';

    protected $table = 'action_credit_cost';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'default_cost' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        $flush = static function () {
            if (app()->bound(CreditCostService::class)) {
                app(CreditCostService::class)->flushCache();
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
