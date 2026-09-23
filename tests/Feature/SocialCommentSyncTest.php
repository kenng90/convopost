<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialComment;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Services\SocialCommentSyncService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialCommentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_facebook_comments_into_social_comments(): void
    {
        Http::fake([
            'graph.facebook.com/*/comments*' => Http::response([
                'data' => [
                    [
                        'id' => 'cmt_100',
                        'message' => 'Love this offer!',
                        'created_time' => '2026-09-23T10:00:00+0000',
                        'from' => ['id' => 'user_9', 'name' => 'Ada Lovelace'],
                    ],
                ],
            ], 200),
        ]);

        [$company, $post, $pivot] = $this->makePublishedFacebookPost();

        $stats = app(SocialCommentSyncService::class)->syncDue($company->id, 10);

        $this->assertSame(1, $stats['synced']);
        $this->assertSame(1, $stats['upserted']);
        $this->assertDatabaseHas('social_comments', [
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'social_post_account_id' => $pivot->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'cmt_100',
            'author_name' => 'Ada Lovelace',
            'author_external_id' => 'user_9',
            'body' => 'Love this offer!',
        ]);

        // Second sync upserts without duplicating.
        app(SocialCommentSyncService::class)->syncDue($company->id, 10);
        $this->assertSame(1, SocialComment::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_syncs_instagram_comments(): void
    {
        Http::fake([
            'graph.facebook.com/*/comments*' => Http::response([
                'data' => [
                    [
                        'id' => 'ig_cmt_1',
                        'text' => 'Need this in Nairobi',
                        'username' => 'shopper_ke',
                        'timestamp' => '2026-09-23T11:00:00+0000',
                        'from' => ['id' => 'ig_user_1', 'username' => 'shopper_ke'],
                    ],
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $account = SocialAccount::factory()->forProvider('instagram')->create([
            'company_id' => $company->id,
        ]);
        $account->setAccessToken('ig-token');
        $account->save();

        $post = SocialPost::factory()->published()->withDefaultVersion('IG post')->create([
            'company_id' => $company->id,
        ]);

        SocialPostAccount::factory()->create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'provider_post_id' => 'ig_media_22',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $stats = app(SocialCommentSyncService::class)->syncDue($company->id, 10);

        $this->assertSame(1, $stats['synced']);
        $this->assertDatabaseHas('social_comments', [
            'provider' => 'instagram',
            'provider_comment_id' => 'ig_cmt_1',
            'author_username' => 'shopper_ke',
            'body' => 'Need this in Nairobi',
        ]);
    }

    public function test_comments_index_is_read_only_and_company_scoped(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $post = SocialPost::factory()->published()->withDefaultVersion('Scoped post')->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
        ]);

        SocialComment::factory()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'provider' => 'facebook',
            'provider_comment_id' => 'mine_1',
            'author_name' => 'Visible Author',
            'body' => 'Visible comment body',
        ]);

        SocialComment::factory()->create([
            'provider' => 'facebook',
            'provider_comment_id' => 'other_1',
            'author_name' => 'Hidden Author',
            'body' => 'Should not appear',
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.comments.index'))
            ->assertOk()
            ->assertSee('Visible comment body')
            ->assertSee('Visible Author')
            ->assertDontSee('Should not appear')
            ->assertDontSee('Reply');
    }

    /**
     * @return array{0: Company, 1: SocialPost, 2: SocialPostAccount}
     */
    protected function makePublishedFacebookPost(): array
    {
        $company = Company::factory()->create();
        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
        ]);
        $account->setAccessToken('fb-token');
        $account->save();

        $post = SocialPost::factory()->published()->withDefaultVersion('Comment post')->create([
            'company_id' => $company->id,
        ]);

        $pivot = SocialPostAccount::factory()->create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'provider_post_id' => '123_456',
            'status' => 'published',
            'published_at' => now(),
        ]);

        return [$company, $post, $pivot];
    }
}
