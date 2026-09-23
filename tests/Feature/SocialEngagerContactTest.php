<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Contacts\Models\Contact;
use Modules\Social\Models\SocialComment;
use Modules\Social\Models\SocialPost;
use Modules\Social\Services\SocialEngagerContactService;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialEngagerContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_contact_and_channel_identity_from_facebook_comment(): void
    {
        $company = Company::factory()->create();
        $post = SocialPost::factory()->published()->withDefaultVersion('Post')->create([
            'company_id' => $company->id,
        ]);

        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'cmt_1',
            'author_name' => 'Ada Lovelace',
            'author_external_id' => 'fb_user_42',
            'body' => 'Nice post',
        ]);

        Http::fake(); // ensure no outbound network

        $contact = app(SocialEngagerContactService::class)->createFromComment($comment);

        $this->assertSame('Ada Lovelace', $contact->name);
        $this->assertSame('', (string) $contact->phone);
        $this->assertSame($contact->id, $comment->fresh()->contact_id);

        $this->assertDatabaseHas('channel_identities', [
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_id' => 'fb_user_42',
        ]);

        $this->assertSame(0, Conversation::withoutGlobalScopes()->count());
        $this->assertSame(0, Message::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }

    public function test_reuses_existing_channel_identity_and_links_sibling_comments(): void
    {
        $company = Company::factory()->create();

        $existing = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Existing',
            'phone' => '',
            'subscribed' => 1,
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $existing->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => 'ig_user_7',
            'display_name' => 'Existing',
        ]);

        $first = SocialComment::factory()->forProvider('instagram')->create([
            'company_id' => $company->id,
            'author_external_id' => 'ig_user_7',
            'author_username' => 'shopper_ke',
            'author_name' => 'Shopper KE',
            'provider_comment_id' => 'ig_cmt_a',
        ]);

        $second = SocialComment::factory()->forProvider('instagram')->create([
            'company_id' => $company->id,
            'author_external_id' => 'ig_user_7',
            'author_username' => 'shopper_ke',
            'provider_comment_id' => 'ig_cmt_b',
        ]);

        $contact = app(SocialEngagerContactService::class)->createFromComment($first);

        $this->assertSame($existing->id, $contact->id);
        $this->assertSame($existing->id, $first->fresh()->contact_id);
        $this->assertSame($existing->id, $second->fresh()->contact_id);
        $this->assertSame(1, Contact::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_owner_can_save_engager_as_contact_from_comments_ui(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $comment = SocialComment::factory()->create([
            'company_id' => $company->id,
            'provider' => 'facebook',
            'author_name' => 'UI Engager',
            'author_external_id' => 'fb_ui_9',
            'provider_comment_id' => 'ui_cmt_1',
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->from(route('social.comments.index'))
            ->post(route('social.comments.create-contact', $comment))
            ->assertRedirect(route('social.comments.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($comment->fresh()->contact_id);
        $this->assertDatabaseHas('contacts', [
            'id' => $comment->fresh()->contact_id,
            'company_id' => $company->id,
            'name' => 'UI Engager',
            'phone' => '',
        ]);
    }
}
