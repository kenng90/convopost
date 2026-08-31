<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TiktokMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
        config(['settings.enable_credits' => false]);
    }

    public function test_tiktok_webhook_creates_message_conversation_and_thread_id(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'tt-verify-token';

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_account_id' => 'biz-open-1',
            'display_name' => 'TikTok',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'tt-token',
                'business_id' => 'biz-open-1',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'event' => 'im_receive_msg',
            'user_openid' => 'biz-open-1',
            'create_time' => 1710000000,
            'content' => json_encode([
                'conversation_id' => 'cid-tt-1',
                'message_id' => 'mid.TT_IN_001',
                'sender' => 'user-open-55',
                'sender_nickname' => 'TikTok User',
                'message_type' => 'TEXT',
                'text' => ['body' => 'TikTok hello'],
                'timestamp' => 1710000000,
            ]),
        ];

        $this->postJson('/webhook/messaging/tiktok/receive/'.$webhookToken, $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'value' => 'TikTok hello',
            'fb_message_id' => 'mid.TT_IN_001',
        ]);

        $this->assertDatabaseHas('conversations', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_participant_id' => 'user-open-55',
            'external_thread_id' => 'cid-tt-1',
            'channel_connection_id' => $connection->id,
        ]);

        $this->assertDatabaseHas('channel_identities', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_id' => 'user-open-55',
        ]);
    }

    public function test_tiktok_challenge_verify_echoes_query(): void
    {
        $company = Company::factory()->create();
        $webhookToken = 'tt-challenge-token';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_account_id' => 'biz-open-1',
            'display_name' => 'TikTok',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'tt-token',
                'business_id' => 'biz-open-1',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $this->get('/webhook/messaging/tiktok/receive/'.$webhookToken.'?challenge=tt-echo-99')
            ->assertOk()
            ->assertSee('tt-echo-99');
    }

    public function test_tiktok_send_echo_event_is_acknowledged_without_storing(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'tt-echo-token';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_account_id' => 'biz-open-1',
            'display_name' => 'TikTok',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'tt-token',
                'business_id' => 'biz-open-1',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $this->postJson('/webhook/messaging/tiktok/receive/'.$webhookToken, [
            'event' => 'im_send_msg',
            'user_openid' => 'biz-open-1',
            'content' => json_encode([
                'conversation_id' => 'cid-tt-1',
                'message_id' => 'mid.TT_ECHO',
            ]),
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('messages', [
            'fb_message_id' => 'mid.TT_ECHO',
        ]);
    }

    public function test_tiktok_outbound_send_success(): void
    {
        Http::fake([
            'business-api.tiktok.com/*' => Http::response([
                'code' => 0,
                'message' => 'ok',
                'data' => ['message' => ['message_id' => 'mid.TT_SENT_001']],
            ], 200),
        ]);

        [$owner, $company, $contact] = $this->makeTiktokThread(lastClientReplyAt: now()->subHour());

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Reply on TikTok', false);

        $this->assertNotSame(5, (int) $message->status);
        $this->assertSame('mid.TT_SENT_001', $message->fb_message_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/business/message/send/')
                && $request->hasHeader('Access-Token', 'tt-token')
                && $request['recipient_type'] === 'CONVERSATION'
                && $request['recipient'] === 'cid-tt-1'
                && $request['message_type'] === 'TEXT'
                && data_get($request->data(), 'text.body') === 'Reply on TikTok';
        });
    }

    public function test_tiktok_outbound_respects_48_hour_window(): void
    {
        [$owner, $company, $contact] = $this->makeTiktokThread(lastClientReplyAt: now()->subDays(3));

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Too late', false);

        $this->assertSame(5, (int) $message->status);
        $this->assertStringContainsString('window', strtolower((string) $message->error));
    }

    public function test_tiktok_outbound_blocks_eleventh_consecutive_reply(): void
    {
        Http::fake([
            'business-api.tiktok.com/*' => Http::response([
                'code' => 0,
                'message' => 'ok',
                'data' => ['message' => ['message_id' => 'mid.should-not-send']],
            ], 200),
        ]);

        [$owner, $company, $contact, $conversation] = $this->makeTiktokThread(lastClientReplyAt: now()->subHour());

        for ($i = 0; $i < 10; $i++) {
            Message::withoutGlobalScope(CompanyScope::class)->create([
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'company_id' => $company->id,
                'channel' => MessagingChannelType::Tiktok->value,
                'value' => 'prior '.$i,
                'is_message_by_contact' => false,
                'status' => 2,
                'buttons' => '[]',
                'components' => '',
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Eleventh reply', false);

        $this->assertSame(5, (int) $message->status);
        $this->assertStringContainsString('10 replies', (string) $message->error);
    }

    public function test_admin_tiktok_setup_redirects_to_whatsapp_setup(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('tiktok.setup'))
            ->assertRedirect(route('whatsapp.setup'));
    }

    /**
     * @return array{0: User, 1: Company, 2: Contact, 3: Conversation}
     */
    private function makeTiktokThread(\DateTimeInterface $lastClientReplyAt): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_account_id' => 'biz-open-1',
            'display_name' => 'TikTok',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'tt-token',
                'business_id' => 'biz-open-1',
            ],
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'TikTok User',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_id' => 'user-open-55',
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_participant_id' => 'user-open-55',
            'external_thread_id' => 'cid-tt-1',
            'last_client_reply_at' => $lastClientReplyAt,
        ]);

        return [$owner, $company, $contact, $conversation];
    }
}
