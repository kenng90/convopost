<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Platform\Customer360Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Customer360ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer360_includes_campaign_history_without_status_column(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Campaign Contact',
            'phone' => '254711111111',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        $campaign = Campaign::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Welcome message',
            'send_to' => 1,
            'sended_to' => 1,
            'delivered_to' => 1,
            'read_by' => 0,
            'is_active' => true,
            'variables' => '{}',
            'variables_match' => '{}',
        ]);

        $payload = app(Customer360Service::class)->forContact($company, $contact);

        $this->assertCount(1, $payload['campaigns']);
        $this->assertSame($campaign->id, $payload['campaigns'][0]['id']);
        $this->assertSame('Welcome message', $payload['campaigns'][0]['name']);
        $this->assertSame('Sent', $payload['campaigns'][0]['status']);
        $this->assertSame(1, $payload['campaigns'][0]['delivered']);
    }
}
