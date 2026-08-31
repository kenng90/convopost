<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Http\Middleware\EnsureOwnerIsOnPROPlan;
use App\Http\Middleware\EnsurePlanCapability;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use App\Models\Plans;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Http\Middleware\CheckPlan;
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

    public function test_tiktok_setup_store_subscribes_webhooks_and_stores_expiry(): void
    {
        Http::fake([
            '*/business/webhook/update/' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'data' => [],
            ], 200),
        ]);

        config([
            'services.tiktok.app_id' => 'tt-app-id',
            'services.tiktok.app_secret' => 'tt-app-secret',
        ]);

        [$owner, $company] = $this->makeTiktokOwnerWithPlan();

        $this->withoutMiddleware([
            EnsureOwnerIsOnPROPlan::class,
            EnsurePlanCapability::class,
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('tiktok.setup.store'), [
                'business_id' => 'biz-open-setup',
                'access_token' => 'access-setup',
                'refresh_token' => 'refresh-setup',
                'webhook_token' => 'wh-setup-token',
            ])
            ->assertRedirect(route('tiktok.setup'))
            ->assertSessionHas('status');

        $connection = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Tiktok->value)
            ->first();

        $this->assertNotNull($connection);
        $this->assertSame('refresh-setup', $connection->credential('refresh_token'));
        $this->assertNotEmpty($connection->credential('access_token_expires_at'));
        $this->assertSame('wh-setup-token', $connection->webhook_token);

        Http::assertSentCount(3);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/business/webhook/update/')
                && $request['app_id'] === 'tt-app-id'
                && $request['secret'] === 'tt-app-secret'
                && $request['event_type'] === 'im_receive_msg'
                && str_contains((string) $request['callback_url'], '/webhook/messaging/tiktok/receive/wh-setup-token');
        });
    }

    public function test_tiktok_refresh_tokens_command_renews_due_access_token(): void
    {
        config([
            'services.tiktok.app_id' => 'tt-app-id',
            'services.tiktok.app_secret' => 'tt-app-secret',
        ]);

        Http::fake([
            '*/tt_user/oauth2/refresh_token/' => Http::response([
                'code' => 0,
                'message' => 'OK',
                'data' => [
                    'access_token' => 'new-access',
                    'refresh_token' => 'new-refresh',
                    'expires_in' => 86400,
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_account_id' => 'biz-open-1',
            'display_name' => 'TikTok',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'old-access',
                'business_id' => 'biz-open-1',
                'refresh_token' => 'old-refresh',
                'access_token_expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]);

        $this->artisan('tiktok:refresh-tokens')
            ->expectsOutputToContain('1 refreshed')
            ->assertSuccessful();

        $connection->refresh();
        $this->assertSame('new-access', $connection->accessToken());
        $this->assertSame('new-refresh', $connection->credential('refresh_token'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/tt_user/oauth2/refresh_token/')
                && $request['refresh_token'] === 'old-refresh'
                && $request['client_id'] === 'tt-app-id';
        });
    }

    public function test_tiktok_refresh_tokens_skips_when_not_due(): void
    {
        config([
            'services.tiktok.app_id' => 'tt-app-id',
            'services.tiktok.app_secret' => 'tt-app-secret',
        ]);

        Http::fake();

        $company = Company::factory()->create();
        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Tiktok->value,
            'external_account_id' => 'biz-open-1',
            'display_name' => 'TikTok',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'still-valid',
                'business_id' => 'biz-open-1',
                'refresh_token' => 'refresh-keep',
                'access_token_expires_at' => now()->addHours(20)->toIso8601String(),
            ],
        ]);

        $this->artisan('tiktok:refresh-tokens')
            ->expectsOutputToContain('1 skipped')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_tiktok_only_workspace_can_open_inbox_with_channel_tab(): void
    {
        [$owner, $company] = $this->makeTiktokOwnerWithPlan();

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
        ]);

        $this->withoutMiddleware([
            EnsureOwnerIsOnPROPlan::class,
            CheckPlan::class,
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('chat.index'))
            ->assertOk()
            ->assertSee('tiktok', false);
    }

    public function test_platform_webhook_token_accepts_inbound_matched_by_business_id(): void
    {
        Event::fake();

        config(['services.tiktok.webhook_token' => 'platform-tt-token']);

        $company = Company::factory()->create();
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
            'webhook_token' => 'company-specific-token',
        ]);

        $this->postJson('/webhook/messaging/tiktok/receive/platform-tt-token', [
            'event' => 'im_receive_msg',
            'user_openid' => 'biz-open-1',
            'content' => json_encode([
                'conversation_id' => 'cid-platform',
                'message_id' => 'mid.TT_PLATFORM',
                'sender' => 'user-open-77',
                'sender_nickname' => 'Platform User',
                'message_type' => 'TEXT',
                'text' => ['body' => 'Via platform token'],
            ]),
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'fb_message_id' => 'mid.TT_PLATFORM',
            'value' => 'Via platform token',
        ]);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function makeTiktokOwnerWithPlan(): array
    {
        $plan = Plans::create([
            'name' => 'Pro TikTok',
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);
        $plan->setConfig('capabilities', json_encode(['inbox', 'inbox_tiktok']));

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
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
