<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\Agents\ActionAgentService;
use App\Services\Agents\AgentEvalService;
use App\Services\Trust\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgentEvalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eval_suite_covers_handoff_catalog_booking_and_payment(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Eval',
            'phone' => '+254700000222',
        ]);

        $report = app(AgentEvalService::class)->run($company, $contact);

        $this->assertSame(0, $report['failed'], json_encode($report['results']));
        $this->assertSame(4, $report['passed']);
    }

    public function test_payment_intent_without_confirm_does_not_create_invoice(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Pay',
            'phone' => '+254700000223',
        ]);

        $result = app(ActionAgentService::class)->ruleBased($company, $contact, 'Charge me 500 for the consultation');

        $this->assertContains('send_payment', $result['tools_used']);
        $this->assertFalse($result['handoff']);
        $this->assertDatabaseMissing('invoices', ['company_id' => $company->id]);
    }

    public function test_opt_out_blocks_payment_tool(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Out',
            'phone' => '+254700000224',
        ]);
        app(ConsentService::class)->record($company, $contact, 'opt_out', 'whatsapp', 'test');

        $result = app(ActionAgentService::class)->ruleBased($company, $contact, 'Yes charge me 500');

        $this->assertContains('send_payment', $result['tools_used']);
        $this->assertDatabaseMissing('invoices', ['company_id' => $company->id]);
    }
}
