<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Messaging\MetaCommentReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MetaCommentReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
        config(['settings.enable_credits' => false]);
    }

    public function test_facebook_comment_webhook_creates_conversation_with_comment_metadata(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'msgr-comment-token';
        $commentedAt = now()->subMinutes(5)->timestamp;

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
            'entry' => [[
                'id' => 'page-111',
                'time' => $commentedAt,
                'changes' => [[
                    'field' => 'feed',
                    'value' => [
                        'item' => 'comment',
                        'verb' => 'add',
                        'comment_id' => '111_555',
                        'post_id' => '111_444',
                        'message' => 'Is this still available?',
                        'from' => [
                            'id' => 'fb-commenter-9',
                            'name' => 'Sam Buyer',
                        ],
                        'created_time' => $commentedAt,
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhook/messaging/messenger/receive/'.$webhookToken, $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'value' => 'Is this still available?',
            'fb_message_id' => 'comment:111_555',
            'extra' => MetaCommentReply::EXTRA_INBOUND,
        ]);

        $conversation = Conversation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('external_participant_id', 'fb-commenter-9')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame('111_555', $conversation->commentId());
        $this->assertFalse($conversation->canDirectMessage());
        $this->assertTrue($conversation->canPrivateCommentReply());
    }

    public function test_instagram_comment_webhook_creates_comment_conversation(): void
    {
        Event::fake();

        $company = Company::factory()->create();
        $webhookToken = 'ig-comment-token';

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-999',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-999',
                'instagram_account_id' => '17841401947499512',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => '17841401947499512',
                'time' => 1710000000,
                'changes' => [[
                    'field' => 'comments',
                    'value' => [
                        'id' => 'igc-100',
                        'text' => 'Price please',
                        'from' => [
                            'id' => 'ig-commenter-3',
                            'username' => 'buyer3',
                        ],
                        'media' => ['id' => 'media-9'],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhook/messaging/instagram/receive/'.$webhookToken, $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'value' => 'Price please',
            'fb_message_id' => 'comment:igc-100',
            'extra' => MetaCommentReply::EXTRA_INBOUND,
        ]);

        $conversation = Conversation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('external_participant_id', 'ig-commenter-3')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame('igc-100', $conversation->commentId());
        $this->assertSame('media-9', data_get($conversation->metadata, 'media_id'));
    }

    public function test_public_facebook_comment_reply_posts_to_comments_endpoint(): void
    {
        Http::fake([
            'graph.facebook.com/*/111_555/comments' => Http::response(['id' => '111_999'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        [$contact] = $this->makeCommentConversation(
            $company,
            MessagingChannelType::Messenger,
            'page-111',
            'fb-commenter-9',
            '111_555',
        );

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Yes, still available', false, false, 'TEXT', null, MetaCommentReply::EXTRA_PUBLIC);

        $this->assertSame('111_999', $message->fb_message_id);
        $this->assertSame(2, (int) $message->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/111_555/comments')
                && data_get($request->data(), 'message') === 'Yes, still available';
        });
    }

    public function test_private_instagram_comment_reply_uses_comment_id_recipient(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.private.001'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        [$contact, $conversation] = $this->makeCommentConversation(
            $company,
            MessagingChannelType::Instagram,
            'page-999',
            'ig-commenter-3',
            'igc-100',
        );

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Thanks, sending details', false, false, 'TEXT', null, MetaCommentReply::EXTRA_PRIVATE);

        $this->assertSame('mid.private.001', $message->fb_message_id);
        $this->assertTrue((bool) data_get($conversation->fresh()->metadata, 'private_reply_sent'));
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/me/messages')
                && data_get($request->data(), 'recipient.comment_id') === 'igc-100'
                && data_get($request->data(), 'message.text') === 'Thanks, sending details';
        });
    }

    public function test_public_comment_reply_skips_expired_24_hour_window(): void
    {
        Http::fake([
            'graph.facebook.com/*/igc-old/replies' => Http::response(['id' => 'igc-reply-1'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        [$contact] = $this->makeCommentConversation(
            $company,
            MessagingChannelType::Instagram,
            'page-999',
            'ig-commenter-old',
            'igc-old',
            now()->subDays(2),
        );

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Public after 24h', false, false, 'TEXT', null, MetaCommentReply::EXTRA_PUBLIC);

        $this->assertSame('igc-reply-1', $message->fb_message_id);
        $this->assertSame(2, (int) $message->status);
    }

    public function test_expired_private_comment_window_is_rejected(): void
    {
        Http::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        [$contact] = $this->makeCommentConversation(
            $company,
            MessagingChannelType::Messenger,
            'page-111',
            'fb-commenter-old',
            '111_old',
            now()->subDays(10),
        );

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Too late', false, false, 'TEXT', null, MetaCommentReply::EXTRA_PRIVATE);

        $this->assertSame(5, (int) $message->status);
        $this->assertStringContainsString('7-day', (string) $message->error);
        Http::assertNothingSent();
    }

    public function test_comment_only_thread_defaults_to_public_reply(): void
    {
        Http::fake([
            'graph.facebook.com/*/111_555/comments' => Http::response(['id' => '111_default'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        [$contact] = $this->makeCommentConversation(
            $company,
            MessagingChannelType::Messenger,
            'page-111',
            'fb-commenter-9',
            '111_555',
        );

        $this->actingAs($owner);
        session(['company_id' => $company->id]);

        $message = $contact->sendMessage('Default public', false);

        $this->assertSame(MetaCommentReply::EXTRA_PUBLIC, $message->extra);
        $this->assertSame('111_default', $message->fb_message_id);
    }

    /**
     * @return array{0: Contact, 1: Conversation}
     */
    private function makeCommentConversation(
        Company $company,
        MessagingChannelType $channel,
        string $pageId,
        string $participantId,
        string $commentId,
        $commentReceivedAt = null,
    ): array {
        $commentReceivedAt = $commentReceivedAt ?? now()->subHour();

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => $channel->value,
            'external_account_id' => $pageId,
            'display_name' => $channel->label(),
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => $pageId,
                'token_is_page' => true,
                'instagram_account_id' => $channel === MessagingChannelType::Instagram ? '17841401947499512' : null,
            ],
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Commenter',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => $channel->value,
            'external_id' => $participantId,
        ]);

        $conversation = Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => $channel->value,
            'external_participant_id' => $participantId,
            'last_client_reply_at' => $commentReceivedAt,
            'metadata' => [
                'source' => MetaCommentReply::SOURCE_COMMENT,
                'comment_id' => $commentId,
                'comment_received_at' => $commentReceivedAt->toIso8601String(),
                'has_direct_message' => false,
            ],
        ]);

        return [$contact, $conversation];
    }
}
