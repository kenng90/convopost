<?php

namespace Modules\Voiceflow\Database\Seeds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class VoiceflowDatabaseSeeder extends Seeder
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
