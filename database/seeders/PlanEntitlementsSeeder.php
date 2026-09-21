<?php

namespace Database\Seeders;

use App\Models\Plans;
use Illuminate\Database\Seeder;

class PlanEntitlementsSeeder extends Seeder
{
    /**
     * Seed or update the four repositioned plan tiers with limits, plugins, and capabilities.
     */
    public function run(): void
    {
        foreach (config('plan-entitlements.tiers', []) as $tier) {
            $plan = Plans::withTrashed()->firstOrNew(['name' => $tier['name']]);

            $plan->fill([
                'price' => $tier['price'],
                'period' => 1,
                'description' => $tier['description'],
                'features' => $tier['features'],
                'limit_items' => $tier['limit_items'],
                'limit_views' => $tier['limit_views'],
                'limit_orders' => $tier['limit_orders'],
                'limit_catalog_items' => $tier['limit_catalog_items'],
                'limit_agents' => $tier['limit_agents'] ?? 0,
                'limit_companies' => $tier['limit_companies'] ?? 0,
                'limit_integrations' => $tier['limit_integrations'] ?? 0,
                'included_agent_seats' => $tier['included_agent_seats'] ?? 0,
                'agent_seat_price' => $tier['agent_seat_price'] ?? 0,
                'included_companies' => $tier['included_companies'] ?? 0,
                'company_seat_price' => $tier['company_seat_price'] ?? 0,
                'enable_ordering' => 1,
            ]);

            if (! $plan->exists) {
                $plan->paddle_id = '';
                $plan->stripe_id = null;
                $plan->paypal_id = null;
                $plan->mollie_id = null;
                $plan->paystack_id = null;
            }

            $plan->save();

            if ($tier['plugins'] === null) {
                $plan->setConfig('plugins', null);
            } else {
                $plan->setConfig('plugins', json_encode($tier['plugins']));
            }

            if ($tier['capabilities'] === null) {
                $plan->setConfig('capabilities', null);
            } else {
                $plan->setConfig('capabilities', json_encode($tier['capabilities']));
            }

            $plan->setConfig(
                'managed_ai_monthly_credits',
                (string) ($tier['managed_ai_monthly_credits'] ?? config('managed-ai.default_monthly_credits', 0))
            );

            if (array_key_exists('limit_social_accounts', $tier)) {
                $plan->setConfig('limit_social_accounts', (string) $tier['limit_social_accounts']);
            }

            if (array_key_exists('limit_social_posts', $tier)) {
                $plan->setConfig('limit_social_posts', (string) $tier['limit_social_posts']);
            }
        }
    }
}
