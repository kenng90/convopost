<?php

namespace Modules\Wpbox\Database\Seeds;

use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class PricingPlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Model::unguard();

        $this->call(PlanEntitlementsSeeder::class);

        Model::reguard();
    }
}
