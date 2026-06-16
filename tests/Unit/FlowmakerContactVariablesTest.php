<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\ContactState;
use Modules\Wpbox\Models\Contact as WpboxContact;
use Tests\TestCase;

class FlowmakerContactVariablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_variables_replaces_flow_state_placeholders_from_cache(): void
    {
        $company = Company::factory()->create();

        $contact = WpboxContact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Jane',
            'phone' => '254700000099',
            'email' => 'jane@example.com',
            'last_message' => 'Hi there',
            'company_id' => $company->id,
        ]);

        ContactState::create([
            'contact_id' => $contact->id,
            'flow_id' => 10,
            'state' => 'order_id',
            'value' => 'ORD-123',
        ]);

        $flowmakerContact = Contact::withoutGlobalScopes()->find($contact->id);
        $flowmakerContact->primeFlowStateCache(10);

        $result = $flowmakerContact->changeVariables('Order {{order_id}} for {{contact_name}} and {{sku}}', 10);

        $this->assertSame('Order ORD-123 for Jane and {{sku}}', $result);
    }
}
