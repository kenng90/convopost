<?php

namespace Modules\Emailwpbox\Database\Seeds;

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
            DB::table('configs')->insert([
                // Email Template 1: Welcome Email
                [
                    'value' => 'Welcome to Mobidonia – Let\'s Get Started!',
                    'key' => 'EMAIL_TEMPLATE_1_SUBJECT',
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'value' => "Hi {name},  \r\n\r\nWelcome to Mobidonia! We're thrilled to have you on board.  \r\n\r\nOur mission is to help businesses like yours grow with innovative tools and strategies.  \r\n\r\nHere's how to get started:  \r\n1. Explore our features tailored to your needs.  \r\n2. Schedule a free demo with our team.  \r\n3. Check out our knowledge base for quick tips.  \r\n\r\n👉 [Get Started Now](https://mobidonia.com)  \r\n\r\nNeed help? Our support team is here for you at [support@mobidonia.com].  \r\n\r\nCheers,  \r\nDaniel  \r\nFounder, Mobidonia",
                    'key' => 'EMAIL_TEMPLATE_1_BODY',
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                // Email Template 2: Lead Nurturing Email
                [
                    'value' => '{name}, Unlock Your Full Potential with Mobidonia',
                    'key' => 'EMAIL_TEMPLATE_2_SUBJECT',
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'value' => "Hi {name},  \r\n\r\nWe noticed you’re exploring Mobidonia, and we wanted to share how others like you are achieving amazing results:  \r\n\r\n- **[Case Study 1]:** Increased customer engagement by 40% in just 2 months.  \r\n- **[Case Study 2]:** Streamlined marketing workflows, saving 15+ hours weekly.  \r\n\r\nReady to see what Mobidonia can do for you?  \r\n\r\n👉 [Schedule Your Free Demo](https://mobidonia.com/demo)  \r\n\r\nLet’s work together to transform your business today!  \r\n\r\nBest regards,  \r\nDaniel  \r\nFounder, Mobidonia",
                    'key' => 'EMAIL_TEMPLATE_2_BODY',
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                // Email Template 3: Re-engagement Email
                [
                    'value' => '{name}, Let’s Reignite Your Success with Mobidonia',
                    'key' => 'EMAIL_TEMPLATE_3_SUBJECT',
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'value' => "Hi {name},  \r\n\r\nWe noticed you haven’t been active recently, and we’d love to help you get back on track!  \r\n\r\nHere’s what’s new at Mobidonia:  \r\n- **Enhanced Features:** Boost productivity with our latest updates.  \r\n- **Customer Success Stories:** Learn how businesses like yours are thriving.  \r\n\r\nLet’s reconnect and explore how Mobidonia can help you achieve your goals.  \r\n\r\n👉 [Book a Call Now](https://mobidonia.com/call)  \r\n\r\nYour success is our priority. Let us know how we can assist you!  \r\n\r\nWarm regards,  \r\nDaniel  \r\nFounder, Mobidonia",
                    'key' => 'EMAIL_TEMPLATE_3_BODY',
                    'model_type' => 'App\\Models\\Company',
                    'model_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
            
        } catch(\Exception $e) {
           // Log::error('Error seeding email templates: ' . $e->getMessage());
        }

        Model::reguard();
    }
}
