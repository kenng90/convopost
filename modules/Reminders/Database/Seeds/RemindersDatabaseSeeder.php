<?php

namespace Modules\Reminders\Database\Seeds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Modules\Reminders\Models\Source;

class RemindersDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        Model::reguard();
    }
}
