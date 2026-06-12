<?php

namespace Database\Seeders;

use App\Services\Billing\SyncCreditActions;
use Illuminate\Database\Seeder;

class CreditActionCostsSeeder extends Seeder
{
    public function run(): void
    {
        app(SyncCreditActions::class)->sync(onlyMissing: false);
    }
}
