<?php

namespace Tests\Unit;

use App\Models\CatalogItemUsage;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\Plans;
use App\Models\User;
use App\Services\CatalogItemPlanLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogItemPlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_limit_uses_active_catalog_items_not_historical_usage_records(): void
    {
        $plan = Plans::create([
            'name' => 'Starter',
            'limit_items' => 0,
            'limit_orders' => 0,
            'price' => 10,
            'period' => 1,
            'description' => 'Test plan',
            'features' => 'Test',
            'limit_catalog_items' => 21,
        ]);

        $user = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->update(['company_id' => $company->id]);

        ListCatalog::create([
            'company_id' => $company->id,
            'name' => 'Products',
            'version' => 1,
            'items' => [
                ['id' => '1', 'title' => 'Item A'],
                ['id' => '2', 'title' => 'Item B'],
            ],
            'columns' => ['id', 'title'],
            'source' => 'manual',
        ]);

        CatalogItemUsage::create([
            'company_id' => $company->id,
            'quantity' => 55,
        ]);

        $service = new CatalogItemPlanLimit;
        $summary = $service->getUsageSummary($company);

        $this->assertSame(2, $summary['used']);
        $this->assertSame(21, $summary['limit']);
        $this->assertTrue($service->canAdd($company, 19));
        $this->assertFalse($service->canAdd($company, 20));
    }

    public function test_deleting_catalog_items_frees_capacity(): void
    {
        $plan = Plans::create([
            'name' => 'Starter',
            'limit_items' => 0,
            'limit_orders' => 0,
            'price' => 10,
            'period' => 1,
            'description' => 'Test plan',
            'features' => 'Test',
            'limit_catalog_items' => 2,
        ]);

        $user = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->update(['company_id' => $company->id]);

        $catalog = ListCatalog::create([
            'company_id' => $company->id,
            'name' => 'Products',
            'version' => 1,
            'items' => [
                ['id' => '1', 'title' => 'Item A'],
                ['id' => '2', 'title' => 'Item B'],
            ],
            'columns' => ['id', 'title'],
            'source' => 'manual',
        ]);

        $service = new CatalogItemPlanLimit;
        $this->assertFalse($service->canAdd($company, 1));

        $catalog->items = [['id' => '1', 'title' => 'Item A']];
        $catalog->save();

        $this->assertTrue($service->canAdd($company, 1));
        $this->assertSame(1, $service->getUsageSummary($company)['used']);
    }
}
