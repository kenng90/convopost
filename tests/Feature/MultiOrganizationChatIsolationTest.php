<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiOrganizationChatIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_incoming_webhook_message_is_stored_for_webhook_organisation_not_active_session_org(): void
    {
        Event::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org A']);
        $companyB = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org B']);
        $owner->update(['company_id' => $companyA->id]);

        $wabaId = '123456789012345';
        $companyB->setConfig('whatsapp_business_account_id', $wabaId);

        $sharedPhone = '254712345678';

        $contactA = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => $sharedPhone,
            'company_id' => $companyA->id,
            'has_chat' => true,
        ]);

        $contactB = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => $sharedPhone,
            'company_id' => $companyB->id,
            'has_chat' => true,
        ]);

        $token = $owner->createToken('webhook-test')->plainTextToken;

        $payload = [
            'entry' => [
                [
                    'id' => $wabaId,
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => 'ORG_B_PHONE_ID',
                                ],
                                'messages' => [
                                    [
                                        'from' => $sharedPhone,
                                        'id' => 'wamid.TEST_MESSAGE_001',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello from Org B'],
                                    ],
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Customer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $companyB->setConfig('whatsapp_phone_number_id', 'ORG_B_PHONE_ID');

        $this->withSession(['company_id' => $companyA->id])
            ->postJson('/webhook/wpbox/receive/'.$token, $payload)
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'contact_id' => $contactB->id,
            'company_id' => $companyB->id,
            'value' => 'Hello from Org B',
            'fb_message_id' => 'wamid.TEST_MESSAGE_001',
        ]);

        $this->assertDatabaseMissing('messages', [
            'contact_id' => $contactA->id,
            'fb_message_id' => 'wamid.TEST_MESSAGE_001',
        ]);
    }

    public function test_webhook_routes_by_phone_number_id_when_two_orgs_share_waba(): void
    {
        Event::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org A']);
        $companyB = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org B']);

        $sharedWaba = '999888777666555';
        $companyA->setConfig('whatsapp_business_account_id', $sharedWaba);
        $companyA->setConfig('whatsapp_phone_number_id', 'PHONE_ORG_A');
        $companyB->setConfig('whatsapp_business_account_id', $sharedWaba);
        $companyB->setConfig('whatsapp_phone_number_id', 'PHONE_ORG_B');

        $contactB = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254711122233',
            'company_id' => $companyB->id,
            'has_chat' => true,
        ]);

        $token = $owner->createToken('webhook-test')->plainTextToken;

        $payload = [
            'entry' => [
                [
                    'id' => $sharedWaba,
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => 'PHONE_ORG_B',
                                ],
                                'messages' => [
                                    [
                                        'from' => '254711122233',
                                        'id' => 'wamid.TEST_SHARED_WABA',
                                        'type' => 'text',
                                        'text' => ['body' => 'Routed to Org B line'],
                                    ],
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Customer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/wpbox/receive/'.$token, $payload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'contact_id' => $contactB->id,
            'company_id' => $companyB->id,
            'value' => 'Routed to Org B line',
        ]);
    }

    public function test_chatlist_only_shows_contacts_for_active_organisation(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org A']);
        $companyB = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org B']);

        $sharedPhone = '254700000099';

        Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Org A Customer',
            'phone' => $sharedPhone,
            'company_id' => $companyA->id,
            'has_chat' => true,
            'last_message' => 'Message for A',
            'last_reply_at' => now(),
        ]);

        Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Org B Customer',
            'phone' => $sharedPhone,
            'company_id' => $companyB->id,
            'has_chat' => true,
            'last_message' => 'Message for B',
            'last_reply_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $companyA->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_message', 'Message for A');

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $companyB->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_message', 'Message for B');
    }

    public function test_chat_messages_endpoint_rejects_contacts_from_other_organisations(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id]);
        $companyB = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $companyA->id]);

        $contactB = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254700000001',
            'company_id' => $companyB->id,
            'has_chat' => true,
        ]);

        Message::withoutGlobalScope(CompanyScope::class)->create([
            'contact_id' => $contactB->id,
            'company_id' => $companyB->id,
            'value' => 'Org B only message',
            'buttons' => '[]',
            'components' => '',
            'status' => 1,
        ]);

        $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $companyA->id])
            ->getJson('/api/wpbox/chat/'.$contactB->id)
            ->assertForbidden();
    }

    public function test_find_contact_by_phone_does_not_match_other_organisations(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id]);
        $companyB = Company::factory()->create(['user_id' => $owner->id]);

        $sharedPhone = '254799999999';

        Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer A',
            'phone' => $sharedPhone,
            'company_id' => $companyA->id,
            'has_chat' => true,
        ]);

        $contactB = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer B',
            'phone' => $sharedPhone,
            'company_id' => $companyB->id,
            'has_chat' => true,
        ]);

        $resolver = new class
        {
            use \Modules\Wpbox\Traits\Contacts;
        };

        $found = $resolver->findContactByPhone($companyB, $sharedPhone);

        $this->assertNotNull($found);
        $this->assertSame($contactB->id, $found->id);
        $this->assertSame($companyB->id, $found->company_id);
    }
}
