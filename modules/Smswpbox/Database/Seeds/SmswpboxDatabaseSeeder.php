<?php

namespace Modules\Smswpbox\Database\Seeds;

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
         //If not demo
         if(!config('settings.is_demo',false)){
            return;
        }
        Model::unguard();
        try {

            DB::table('configs')->insert([
                // SMS Template 1: Welcome SMS
            [
                'value' => 'Welcome to Mobidonia, {name}! We’re thrilled to have you on board. Explore our features and get started today: https://mobidonia.com',
                'key' => 'SMS_TEMPLATE_1',
                'model_type' => 'App\\Models\\Company',
                'model_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            // SMS Template 2: Lead Nurturing SMS
            [
                'value' => 'Hi {name}, ready to grow your business? Discover how Mobidonia can help you achieve your goals. Schedule a free demo now: https://mobidonia.com/demo',
                'key' => 'SMS_TEMPLATE_2',
                'model_type' => 'App\\Models\\Company',
                'model_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            // SMS Template 3: Re-engagement SMS
            [
                'value' => 'Hi {name}, we miss you at Mobidonia! Check out what’s new and let’s reconnect: https://mobidonia.com/call',
                'key' => 'SMS_TEMPLATE_3',
                'model_type' => 'App\\Models\\Company',
                'model_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            // SMS Template 4: Special Offer SMS
            [
                'value' => 'Hi {name}, exclusive offer just for you! Get 20% off your next subscription with Mobidonia. Claim now: https://mobidonia.com/offer',
                'key' => 'SMS_TEMPLATE_4',
                'model_type' => 'App\\Models\\Company',
                'model_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        } catch (\Exception $e) {
            Log::error('Error creating sms templates: ' . $e->getMessage());
        }
        

        Model::reguard();
    }
}
