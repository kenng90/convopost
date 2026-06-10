<?php

namespace Modules\Smswpbox\Database\Seeds;

use App\Services\VendorBrandingScrubber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmswpboxDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (! config('settings.is_demo', false)) {
            return;
        }

        Model::unguard();

        try {
            foreach (VendorBrandingScrubber::smsTemplateConfigs() as $template) {
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
            Log::error('Error creating sms templates: '.$e->getMessage());
        }

        Model::reguard();
    }
}
