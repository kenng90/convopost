<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactInteractiveMessageExtraTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_message_persists_interactive_extra_for_queued_flow_processing(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254700000099',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $listItemId = 'section1-row1_id42_flow7';

        $message = $contact->sendMessage(
            'Swedish Massage',
            true,
            false,
            'TEXT',
            'wamid.LIST_SELECTION_001',
            $listItemId,
        );

        $this->assertSame($listItemId, $message->extra);

        $reloaded = Message::withoutGlobalScopes()->findOrFail($message->id);
        $this->assertSame($listItemId, $reloaded->extra);

        $job = new ProcessFlowMessage(7, $message->id);
        $jobMessage = Message::withoutGlobalScopes()->find($job->messageId);
        $this->assertNotNull($jobMessage);
        $this->assertSame($listItemId, $jobMessage->extra);
    }
}
