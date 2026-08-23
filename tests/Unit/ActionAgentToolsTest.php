<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\Agents\ActionAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActionAgentToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_intent_lists_services(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Booker',
            'phone' => '+254700000333',
        ]);

        $result = app(ActionAgentService::class)->ruleBased($company, $contact, 'Please book me an appointment');

        $this->assertContains('list_services', $result['tools_used']);
        $this->assertNotEmpty($result['reply']);
        $this->assertFalse($result['handoff']);
    }
}
