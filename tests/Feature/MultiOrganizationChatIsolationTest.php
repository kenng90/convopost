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

        $this->withSession(['company_id' => $companyA->id])
            ->postJson('/webhook/wpbox/receive/'.$token, $payload)
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'contact_id' => $contactB->id,
            'company_id' => $companyB->id,
            'value' => 'Hello from Org B',
            'fb_message_id' => 'wamid.TEST_MESSAGE_001',
        ]);

        $contactB->refresh();
        $this->assertSame(0, (int) $contactB->resolved_chat);
        $this->assertSame(1, (int) $contactB->is_last_message_by_contact);
    }

    public function test_chat_list_includes_contact_after_inbound_customer_message(): void
    {
        Event::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $wabaId = '123456789012345';
        $company->setConfig('whatsapp_business_account_id', $wabaId);
        $company->setConfig('whatsapp_phone_number_id', 'phone-123');
        $company->setConfig('whatsapp_permanent_access_token', 'token-123');
        $company->setConfig('whatsapp_webhook_verified', 'yes');
        $company->setConfig('whatsapp_settings_done', 'yes');

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254712345678',
            'company_id' => $company->id,
            'has_chat' => true,
            'resolved_chat' => 1,
        ]);

        $token = $owner->createToken('webhook-test')->plainTextToken;

        $this->postJson('/webhook/wpbox/receive/'.$token, [
            'entry' => [[
                'id' => $wabaId,
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '254712345678',
                            'id' => 'wamid.CHATLIST_TEST',
                            'type' => 'text',
                            'text' => ['body' => 'Need help'],
                        ]],
                        'contacts' => [['profile' => ['name' => 'Customer']]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $response = $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $company->id])
            ->getJson('/api/wpbox/chats/none/1/');

        $response->assertOk()
            ->assertJsonPath('newMessagesCount', 1)
            ->assertJsonCount(1, 'data');

        $this->assertSame($contact->id, $response->json('data.0.id'));
        $this->assertSame(0, (int) $response->json('data.0.resolved_chat'));

        $this->assertDatabaseHas('messages', [
            'contact_id' => $contact->id,
            'fb_message_id' => 'wamid.CHATLIST_TEST',
        ]);
    }

    public function test_chat_list_reopens_unread_closed_contact_on_load(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Closed Unread Customer',
            'phone' => '254799999999',
            'company_id' => $company->id,
            'has_chat' => true,
            'resolved_chat' => 1,
            'is_last_message_by_contact' => 1,
            'last_reply_at' => now(),
            'last_message' => 'Hello?',
        ]);

        $response = $this->actingAs($owner)
            ->withoutMiddleware()
            ->withSession(['company_id' => $company->id])
            ->getJson('/api/wpbox/chats/none/1/?filter=new');

        $response->assertOk()
            ->assertJsonPath('newMessagesCount', 1)
            ->assertJsonCount(1, 'data');

        $this->assertSame($contact->id, $response->json('data.0.id'));

        $contact->refresh();
        $this->assertSame(0, (int) $contact->resolved_chat);
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
