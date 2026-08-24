<?php

namespace Tests\Feature\Collections;

use App\Enums\CollectionStatus;
use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_open_collections(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $plan = Plans::create([
            'name' => 'Pro',
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Acme Clinic']);
        $owner->update(['company_id' => $company->id]);

        Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-BOARD-1',
            'customer_name' => 'Jane Doe',
            'customer_phone' => '254700000001',
            'amount' => 250,
            'currency' => 'KES',
            'status' => 'sent',
            'items' => [],
            'collection_status' => CollectionStatus::Due->value,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('collections.index'));

        $response->assertOk();
        $response->assertSee('INV-BOARD-1');
        $response->assertSee('Jane Doe');
        $response->assertSee('Collections');
    }
}
