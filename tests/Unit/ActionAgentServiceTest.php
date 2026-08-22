<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\Agents\ActionAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActionAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_intent_searches_catalog(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shopper',
            'phone' => '+254711000001',
        ]);

        $result = app(ActionAgentService::class)->ruleBased($company, $contact, 'I want to buy a product from the catalog');

        $this->assertContains('search_catalog', $result['tools_used']);
        $this->assertFalse($result['handoff']);
        $this->assertNotEmpty($result['reply']);
    }
}
