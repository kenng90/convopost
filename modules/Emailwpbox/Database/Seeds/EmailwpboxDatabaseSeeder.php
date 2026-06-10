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
            foreach (VendorBrandingScrubber::emailTemplateConfigs() as $template) {
                DB::table('configs')->updateOrInsert(
                    [
                        'key' => $template['key'],
                        'model_type' => 'App\\Models\\Company',
                        'model_id' => 1,
                    ],
                    [
                        'value' => $template['value'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        } catch (\Exception $e) {
            //
        }

        Model::reguard();
    }
}
