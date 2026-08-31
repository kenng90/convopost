<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChannelWebhookRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_unknown_channel_slug_returns_404(): void
    {
        $this->postJson('/webhook/messaging/not-a-channel/receive/some-token', [])
            ->assertNotFound()
            ->assertJson(['error' => 'Unknown channel']);
    }

    public function test_tiktok_does_not_use_meta_hub_verify(): void
    {
        $this->get('/webhook/messaging/tiktok/receive/not-a-real-token?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'not-a-real-token',
            'hub_challenge' => 'should-not-echo',
        ]))
            ->assertForbidden()
            ->assertDontSee('should-not-echo');
    }

    public function test_tiktok_invalid_token_is_rejected(): void
    {
        $this->postJson('/webhook/messaging/tiktok/receive/wrong-token', [
            'event' => 'im_receive_msg',
        ])
            ->assertForbidden()
            ->assertJson(['error' => 'Invalid token']);
    }

    public function test_meta_adapter_verifies_hub_challenge_with_connection_token(): void
    {
        $company = Company::factory()->create();
        $webhookToken = 'ig-connection-verify-token';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-verify',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-verify',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $this->get('/webhook/messaging/instagram/receive/'.$webhookToken.'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $webhookToken,
            'hub_challenge' => 'ig-challenge-42',
        ]))
            ->assertOk()
            ->assertSee('ig-challenge-42');
    }

    public function test_meta_adapter_rejects_get_without_hub_mode(): void
    {
        $company = Company::factory()->create();
        $webhookToken = 'msgr-no-hub';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => 'page-no-hub',
            'display_name' => 'Messenger',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-no-hub',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $this->get('/webhook/messaging/messenger/receive/'.$webhookToken)
            ->assertForbidden();
    }

    public function test_whatsapp_adapter_does_not_accept_meta_hub_verify_on_unified_route(): void
    {
        $owner = User::factory()->create();
        $token = $owner->createToken('webhook-test')->plainTextToken;

        $this->get('/webhook/messaging/whatsapp/receive/'.$token.'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $token,
            'hub_challenge' => 'wa-should-not-echo',
        ]))
            ->assertForbidden();
    }

    public function test_invalid_token_is_rejected_before_inbound_parse(): void
    {
        Event::fake();

        $company = Company::factory()->create();

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => 'page-auth',
            'display_name' => 'Messenger',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-auth',
            ],
            'webhook_token' => 'real-token',
        ]);

        $this->postJson('/webhook/messaging/messenger/receive/wrong-token', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page-auth',
                    'messaging' => [
                        [
                            'sender' => ['id' => 'fb-user-1'],
                            'recipient' => ['id' => 'page-auth'],
                            'timestamp' => 1710000000000,
                            'message' => [
                                'mid' => 'mid.SHOULD_NOT_PERSIST',
                                'text' => 'blocked',
                            ],
                        ],
                    ],
                ],
            ],
        ])
            ->assertForbidden()
            ->assertJson(['error' => 'Invalid token']);

        $this->assertDatabaseMissing('messages', [
            'fb_message_id' => 'mid.SHOULD_NOT_PERSIST',
        ]);
    }

    public function test_authorized_token_with_unknown_page_is_acknowledged(): void
    {
        Event::fake();

        $admin = User::factory()->create();
        $platformToken = $this->plainSanctumToken($admin);

        $this->postJson('/webhook/messaging/messenger/receive/'.$platformToken, [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page-unknown-other-tenant',
                    'messaging' => [
                        [
                            'sender' => ['id' => 'fb-user-other'],
                            'recipient' => ['id' => 'page-unknown-other-tenant'],
                            'timestamp' => 1710000000000,
                            'message' => [
                                'mid' => 'mid.UNKNOWN_PAGE',
                                'text' => 'should ack not store',
                            ],
                        ],
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('messages', [
            'fb_message_id' => 'mid.UNKNOWN_PAGE',
        ]);
    }

    private function plainSanctumToken(User $user): string
    {
        $token = $user->createToken('platform-webhook')->plainTextToken;
        $parts = explode('|', $token);

        return $parts[1] ?? $token;
    }
}
