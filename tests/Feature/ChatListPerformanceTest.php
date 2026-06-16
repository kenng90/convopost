<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Flowmaker\Listeners\RespondOnMessage;
use Modules\Flowmaker\Models\Flow;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_chatlist_does_not_eager_load_messages(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254700000010',
            'company_id' => $company->id,
            'has_chat' => true,
            'last_message' => 'Hello',
            'last_reply_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            Message::withoutGlobalScope(CompanyScope::class)->create([
                'contact_id' => $contact->id,
                'company_id' => $company->id,
                'value' => 'Message '.$i,
                'buttons' => '[]',
                'components' => '',
                'status' => 1,
            ]);
        }

        $response = $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $company->id])
            ->getJson('/api/wpbox/chats/none/1/');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');

        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('messages', $payload);
        $this->assertSame('Hello', $payload['last_message']);
    }

    public function test_chat_messages_supports_cursor_pagination(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254700000011',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $message = Message::withoutGlobalScope(CompanyScope::class)->create([
                'contact_id' => $contact->id,
                'company_id' => $company->id,
                'value' => 'Message '.$i,
                'buttons' => '[]',
                'components' => '',
                'status' => 1,
            ]);
            $ids[] = $message->id;
        }

        $newestId = max($ids);

        $response = $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $company->id])
            ->getJson('/api/wpbox/chat/'.$contact->id.'?before_id='.$newestId.'&limit=1');

        $response->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_respond_on_message_dispatches_queued_flow_jobs(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $flow = Flow::withoutGlobalScopes()->create([
            'name' => 'Test Flow',
            'company_id' => $company->id,
            'flow_data' => json_encode([
                'nodes' => [['id' => '1', 'type' => 'incomingMessage', 'position' => ['x' => 0, 'y' => 0], 'data' => []]],
                'edges' => [],
            ]),
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254700000012',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $message = Message::withoutGlobalScope(CompanyScope::class)->create([
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'value' => 'Hi',
            'buttons' => '[]',
            'components' => '',
            'status' => 1,
            'is_message_by_contact' => true,
            'bot_has_replied' => false,
        ]);
        $message->setRelation('contact', $contact);

        $listener = new RespondOnMessage;
        $listener->handleMessageByContact((object) ['message' => $message]);

        Queue::assertPushed(ProcessFlowMessage::class, function (ProcessFlowMessage $job) use ($flow, $message) {
            return $job->flowId === $flow->id && $job->messageId === $message->id;
        });
    }
}
