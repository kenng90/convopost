<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Support\Offering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Flowmaker\Models\Flow;
use Modules\Social\Models\SocialComment;
use Modules\Social\Models\SocialPost;
use Modules\Social\Services\SocialEngagementInboxService;
use Modules\Wpbox\Events\ContactReplies;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialEngagementInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
    }

    protected function makeCompanyWithMessengerConnection(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => 'page-social-1',
            'display_name' => 'Page',
            'status' => 'connected',
            'credentials' => ['access_token' => 'token', 'page_id' => 'page-social-1'],
            'webhook_token' => 'social-inbox-token',
        ]);

        return [$owner, $company];
    }

    public function test_full_mode_opens_comment_in_inbox_and_fires_contact_replies(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);
        Event::fake([ContactReplies::class]);

        [, $company] = $this->makeCompanyWithMessengerConnection();

        $post = SocialPost::factory()->published()->withDefaultVersion('Offer')->create([
            'company_id' => $company->id,
        ]);

        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'fb_cmt_inbox_1',
            'provider_post_id' => 'page_post_1',
            'author_name' => 'Inbox Buyer',
            'author_external_id' => 'fb_buyer_88',
            'body' => 'Still available?',
        ]);

        Http::fake();

        $result = app(SocialEngagementInboxService::class)->openFromComment($comment);

        $this->assertTrue($result['opened']);
        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'fb_message_id' => 'comment:fb_cmt_inbox_1',
            'value' => 'Still available?',
            'channel' => MessagingChannelType::Messenger->value,
        ]);

        $conversation = Conversation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('external_participant_id', 'fb_buyer_88')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame('fb_cmt_inbox_1', $conversation->commentId());
        $this->assertTrue($conversation->belongsToCommentsInbox());

        Event::assertDispatched(ContactReplies::class);
        Http::assertNothingSent();
    }

    public function test_social_commerce_mode_does_not_open_inbox(): void
    {
        config(['offering.mode' => Offering::MODE_SOCIAL_COMMERCE]);

        [, $company] = $this->makeCompanyWithMessengerConnection();

        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'fb_cmt_dormant',
            'author_external_id' => 'fb_user_dormant',
            'body' => 'Hi',
        ]);

        $result = app(SocialEngagementInboxService::class)->openFromComment($comment);

        $this->assertFalse($result['opened']);
        $this->assertSame('offering_or_config', $result['skipped']);
        $this->assertSame(0, Conversation::withoutGlobalScopes()->count());
        $this->assertSame(0, Message::withoutGlobalScopes()->count());
    }

    public function test_full_mode_dispatches_flow_when_bot_enabled_and_flow_matches(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);
        Queue::fake();

        [, $company] = $this->makeCompanyWithMessengerConnection();
        session(['company_id' => $company->id]);

        Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Social comment flow',
            'is_active' => true,
            'priority' => 10,
            'flow_data' => json_encode([
                'nodes' => [
                    ['id' => '1', 'type' => 'keyword_trigger', 'data' => ['keywords' => ['available']]],
                    ['id' => '2', 'type' => 'message', 'data' => ['text' => 'Yes!']],
                ],
                'edges' => [],
                'supported_channels' => ['messenger', 'instagram'],
            ]),
        ]);

        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'fb_cmt_flow_1',
            'author_name' => 'Flow Buyer',
            'author_external_id' => 'fb_flow_1',
            'body' => 'Is this still available?',
        ]);

        $result = app(SocialEngagementInboxService::class)->openFromComment($comment);

        $this->assertTrue($result['opened']);
        Queue::assertPushed(ProcessFlowMessage::class);
    }

    public function test_owner_can_open_inbox_from_comments_ui_when_full(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);
        Event::fake([ContactReplies::class]);

        [$owner, $company] = $this->makeCompanyWithMessengerConnection();

        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'fb_ui_inbox',
            'author_name' => 'UI Buyer',
            'author_external_id' => 'fb_ui_buyer',
            'body' => 'Price?',
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('social.comments.open-inbox', $comment))
            ->assertRedirect(route('chat.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('messages', [
            'company_id' => $company->id,
            'fb_message_id' => 'comment:fb_ui_inbox',
        ]);
    }

    public function test_open_inbox_skipped_without_channel_connection(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        $company = Company::factory()->create();
        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'fb_no_conn',
            'author_external_id' => 'fb_x',
            'body' => 'Hello',
        ]);

        $result = app(SocialEngagementInboxService::class)->openFromComment($comment);

        $this->assertFalse($result['opened']);
        $this->assertSame('missing_channel_connection', $result['skipped']);
    }
}
