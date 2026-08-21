<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Wpbox\Events\Chatlistchange;
use Modules\Wpbox\Models\Contact;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1ConversationsTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_inbox_assign_resolve_and_notes(): void
    {
        $this->createPublicApiOwner();
        Event::fake([Chatlistchange::class]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->apiCompany->id,
            'name' => 'Inbox Lead',
            'phone' => '+254700000400',
            'has_chat' => 1,
            'resolved_chat' => 0,
        ]);

        $this->getJson('/api/v1/conversations', $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $contact->id);

        $this->postJson('/api/v1/conversations/'.$contact->id.'/assign', [
            'user_id' => $this->apiUser->id,
        ], $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.assigned_user_id', $this->apiUser->id);

        $this->postJson('/api/v1/conversations/'.$contact->id.'/notes', [
            'note' => 'Called the customer',
        ], $this->publicApiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.body', 'Called the customer');

        $this->postJson('/api/v1/conversations/'.$contact->id.'/resolve', [], $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertSame(1, (int) $contact->fresh()->resolved_chat);
    }
}
