<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
    }

    public function test_chatlist_new_filter_returns_only_unread_open_contacts(): void
    {
        $unread = $this->makeContact([
            'name' => 'Unread Customer',
            'phone' => '254700000101',
            'is_last_message_by_contact' => true,
            'resolved_chat' => 0,
        ]);

        $this->makeContact([
            'name' => 'Replied Customer',
            'phone' => '254700000102',
            'is_last_message_by_contact' => false,
            'resolved_chat' => 0,
        ]);

        $response = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=new');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);
    }

    public function test_chatlist_mine_filter_returns_only_assigned_open_contacts(): void
    {
        $assigned = $this->makeContact([
            'name' => 'Assigned Customer',
            'phone' => '254700000103',
            'user_id' => $this->owner->id,
            'resolved_chat' => 0,
        ]);

        $this->makeContact([
            'name' => 'Unassigned Customer',
            'phone' => '254700000104',
            'user_id' => null,
            'is_last_message_by_contact' => true,
            'resolved_chat' => 0,
        ]);

        $response = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=mine');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
    }

    public function test_chatlist_resolved_filter_returns_closed_contacts(): void
    {
        $closed = $this->makeContact([
            'name' => 'Closed Customer',
            'phone' => '254700000105',
            'resolved_chat' => 1,
        ]);

        $this->makeContact([
            'name' => 'Open Customer',
            'phone' => '254700000106',
            'resolved_chat' => 0,
        ]);

        $response = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=resolved');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $closed->id);
    }

    public function test_staff_with_agent_assigned_only_sees_assigned_and_unassigned_inbound_contacts(): void
    {
        $this->company->setConfig('agent_assigned_only', 'true');

        $staff = User::factory()->create(['company_id' => $this->company->id]);
        $staff->assignRole('staff');

        $assigned = $this->makeContact([
            'name' => 'Assigned To Staff',
            'phone' => '254700000107',
            'user_id' => $staff->id,
            'resolved_chat' => 0,
        ]);

        $unassignedInbound = $this->makeContact([
            'name' => 'New Inbound',
            'phone' => '254700000108',
            'user_id' => null,
            'is_last_message_by_contact' => true,
            'resolved_chat' => 0,
        ]);

        $this->makeContact([
            'name' => 'Other Agent Contact',
            'phone' => '254700000109',
            'user_id' => $this->owner->id,
            'resolved_chat' => 0,
        ]);

        $this->makeContact([
            'name' => 'Old Unassigned',
            'phone' => '254700000110',
            'user_id' => null,
            'is_last_message_by_contact' => false,
            'resolved_chat' => 0,
        ]);

        $response = $this->actingAs($staff)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=open');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$assigned->id, $unassignedInbound->id], $ids);
    }

    public function test_chatlist_incremental_cursor_returns_contacts_after_timestamp(): void
    {
        $older = $this->makeContact([
            'name' => 'Older Customer',
            'phone' => '254700000111',
            'last_reply_at' => Carbon::parse('2026-06-20 10:00:00'),
        ]);

        $newer = $this->makeContact([
            'name' => 'Newer Customer',
            'phone' => '254700000112',
            'last_reply_at' => Carbon::parse('2026-06-20 11:00:00'),
        ]);

        $cursor = $older->last_reply_at->toIso8601String();

        $response = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/'.urlencode($cursor).'/1/?filter=open');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id);
    }

    public function test_incremental_cursor_accepts_iso8601_format(): void
    {
        $older = $this->makeContact([
            'name' => 'Older Customer',
            'phone' => '254700000114',
            'last_reply_at' => Carbon::parse('2026-06-20 10:00:00'),
        ]);

        $newer = $this->makeContact([
            'name' => 'Newer Customer',
            'phone' => '254700000115',
            'last_reply_at' => Carbon::parse('2026-06-20 11:00:00'),
        ]);

        $cursor = Carbon::parse('2026-06-20 10:00:00')->toIso8601String();

        $response = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/'.urlencode($cursor).'/1/?filter=open');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id);
    }

    public function test_inbound_message_keeps_contact_in_open_tab(): void
    {
        $contact = $this->makeContact([
            'name' => 'New Texter',
            'phone' => '254700000116',
            'resolved_chat' => 0,
        ]);

        $contact->sendMessage('Hello', true, false, 'TEXT', 'wamid.INBOUND_OPEN_001');

        $contact->refresh();

        $this->assertEquals(0, (int) $contact->resolved_chat);
        $this->assertEquals(1, (int) $contact->is_last_message_by_contact);

        $openResponse = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=open');

        $openResponse->assertOk()
            ->assertJsonPath('data.0.id', $contact->id);

        $closedResponse = $this->actingAs($this->owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $this->company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=resolved');

        $closedResponse->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inbound_message_reopens_previously_closed_contact(): void
    {
        $contact = $this->makeContact([
            'name' => 'Returning Customer',
            'phone' => '254700000117',
            'resolved_chat' => 1,
        ]);

        $contact->sendMessage('I need more help', true, false, 'TEXT', 'wamid.INBOUND_REOPEN_001');

        $contact->refresh();

        $this->assertEquals(0, (int) $contact->resolved_chat);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeContact(array $attributes = []): Contact
    {
        return Contact::withoutGlobalScope(CompanyScope::class)->create(array_merge([
            'name' => 'Customer',
            'phone' => '254700000000',
            'company_id' => $this->company->id,
            'has_chat' => true,
            'last_message' => 'Hello',
            'last_reply_at' => now(),
            'is_last_message_by_contact' => false,
            'resolved_chat' => 0,
        ], $attributes));
    }
}
