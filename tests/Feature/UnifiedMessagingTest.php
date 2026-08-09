<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Messaging\ChannelConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_whatsapp_webhook_creates_conversation_and_links_message(): void
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

        app(ChannelConnectionService::class)->ensureWhatsappConnection($company);

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
                                        'from' => '254712345678',
                                        'id' => 'wamid.TEST_CONV_001',
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello unified messaging'],
                                    ],
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Jane']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/wpbox/receive/'.$token, $payload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'value' => 'Hello unified messaging',
            'fb_message_id' => 'wamid.TEST_CONV_001',
            'channel' => MessagingChannelType::Whatsapp->value,
        ]);

        $message = Message::withoutGlobalScopes()->where('fb_message_id', 'wamid.TEST_CONV_001')->first();
        $this->assertNotNull($message->conversation_id);

        $this->assertDatabaseHas('conversations', [
            'id' => $message->conversation_id,
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Whatsapp->value,
            'external_participant_id' => '254712345678',
        ]);
    }

    public function test_backfill_command_links_existing_messages_to_conversations(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $company->setConfig('whatsapp_phone_number_id', 'phone-123');
        $company->setConfig('whatsapp_permanent_access_token', 'token-123');
        $company->setConfig('whatsapp_business_account_id', 'waba-123');

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Customer',
            'phone' => '254700000099',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        $message = Message::withoutGlobalScope(CompanyScope::class)->create([
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'value' => 'Legacy message',
            'buttons' => '[]',
            'components' => '',
            'status' => 1,
        ]);

        $this->artisan('messaging:backfill-conversations', ['--company' => $company->id])
            ->assertSuccessful();

        $message->refresh();
        $this->assertNotNull($message->conversation_id);
        $this->assertSame(MessagingChannelType::Whatsapp->value, $message->channel);
    }

    public function test_instagram_webhook_creates_message_and_conversation(): void
    {
        Event::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $webhookToken = 'ig-verify-token-123';
        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-999',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-999',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => 'page-999',
                    'time' => 1710000000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'ig-user-555'],
                            'recipient' => ['id' => 'page-999'],
                            'timestamp' => 1710000000000,
                            'message' => [
                                'mid' => 'mid.IG_TEST_001',
                                'text' => 'Instagram hello',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/messaging/instagram/receive/'.$webhookToken, $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'value' => 'Instagram hello',
            'fb_message_id' => 'mid.IG_TEST_001',
        ]);

        $this->assertDatabaseHas('conversations', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_participant_id' => 'ig-user-555',
            'channel_connection_id' => $connection->id,
        ]);
    }

    public function test_instagram_outbound_respects_expired_service_window(): void
    {
        config(['settings.enable_credits' => false]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'IG User',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-999',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-999',
            ],
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_participant_id' => 'ig-user-555',
            'last_client_reply_at' => now()->subDays(10),
        ]);

        \App\Models\Messaging\ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => 'ig-user-555',
        ]);

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Late reply', false);

        $this->assertSame(5, (int) $message->status);
        $this->assertStringContainsString('window', strtolower((string) $message->error));
    }

    public function test_instagram_outbound_send_success(): void
    {
        config(['settings.enable_credits' => false]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.sent.001'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-999',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-999',
                'instagram_account_id' => 'ig-biz-777',
            ],
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'IG User',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_participant_id' => 'ig-user-555',
            'last_client_reply_at' => now()->subHour(),
        ]);

        \App\Models\Messaging\ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => 'ig-user-555',
        ]);

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Thanks for reaching out', false);

        $this->assertSame('mid.sent.001', $message->fb_message_id);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v19.0/page-999/messages'
                && data_get($request->data(), 'recipient.id') === 'ig-user-555'
                && data_get($request->data(), 'message.text') === 'Thanks for reaching out'
                && ! array_key_exists('messaging_type', $request->data());
        });
    }

    public function test_instagram_outbound_rejects_business_account_as_recipient(): void
    {
        config(['settings.enable_credits' => false]);

        Http::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-999',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-999',
                'instagram_account_id' => '17841413486594880',
            ],
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Broken IG User',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_participant_id' => '17841413486594880',
            'last_client_reply_at' => now()->subHour(),
        ]);

        \App\Models\Messaging\ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => '17841413486594880',
        ]);

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Should fail', false);

        $this->assertSame(5, (int) $message->status);
        $this->assertStringContainsString('Invalid recipient id', (string) $message->error);
        Http::assertNothingSent();
    }

    public function test_messenger_webhook_creates_message(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'msgr-token-123';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => 'page-111',
            'display_name' => 'Messenger',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-111',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page-111',
                    'time' => 1710000000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'fb-user-777'],
                            'recipient' => ['id' => 'page-111'],
                            'timestamp' => 1710000000000,
                            'message' => [
                                'mid' => 'mid.MSG_TEST_001',
                                'text' => 'Messenger hello',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/messaging/messenger/receive/'.$webhookToken, $payload)
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'value' => 'Messenger hello',
        ]);
    }

    public function test_instagram_changes_webhook_format_creates_message(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'ig-changes-token-123';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'ig-page-222',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'ig-page-222',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => 'ig-page-222',
                    'time' => 1710000000,
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'sender' => ['id' => 'ig-user-888'],
                                'recipient' => ['id' => 'ig-page-222'],
                                'timestamp' => 1710000000000,
                                'message' => [
                                    'mid' => 'mid.IG_CHANGES_001',
                                    'text' => 'Hello via changes',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/webhook/messaging/instagram/receive/'.$webhookToken, $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'value' => 'Hello via changes',
            'fb_message_id' => 'mid.IG_CHANGES_001',
        ]);
    }

    public function test_messaging_webhook_is_csrf_exempt(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'csrf-exempt-token';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => 'page-csrf',
            'display_name' => 'Messenger',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-csrf',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page-csrf',
                    'messaging' => [
                        [
                            'sender' => ['id' => 'fb-user-csrf'],
                            'recipient' => ['id' => 'page-csrf'],
                            'timestamp' => 1710000000000,
                            'message' => [
                                'mid' => 'mid.CSRF_001',
                                'text' => 'csrf ok',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Simulate Meta (no session / CSRF token) — must not 419.
        $this->call(
            'POST',
            '/webhook/messaging/messenger/receive/'.$webhookToken,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode($payload),
        )->assertOk();

        $this->assertDatabaseHas('messages', [
            'fb_message_id' => 'mid.CSRF_001',
            'value' => 'csrf ok',
        ]);
    }
}
