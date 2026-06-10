<?php

namespace Modules\Emailwpbox\Database\Seeds;

use App\Services\VendorBrandingScrubber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailwpboxDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        try {
            $rows = array_map(function (array $template) {
                return [
                    'value' => $template['value'],
                    'key' => $template['key'],
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, VendorBrandingScrubber::emailTemplateConfigs());

            DB::table('configs')->insert($rows);
        } catch (\Exception $e) {
            //
        }

        Model::reguard();
    }
}
