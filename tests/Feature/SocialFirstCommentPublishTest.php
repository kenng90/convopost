<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Jobs\PublishSocialPostJob;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\FacebookPublisher;
use Modules\Social\Publishing\InstagramPublisher;
use Tests\TestCase;

class SocialFirstCommentPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_publisher_posts_first_comment_after_feed_publish(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => 'page_123_456'], 200),
            'graph.facebook.com/*/comments' => Http::response(['id' => 'comment_789'], 200),
        ]);

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'external_id' => 'page_123',
            'meta' => ['page_id' => 'page_123'],
        ]);
        $account->setAccessToken('page-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'facebook',
            'content' => 'Launch post',
            'first_comment' => 'Link in bio → shop now',
        ]);

        $result = app(FacebookPublisher::class)->publish($account, $version, []);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('page_123_456', $result->providerPostId);
        $this->assertSame('comment_789', $result->meta['first_comment_id'] ?? null);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/comments')
                && data_get($request->data(), 'message') === 'Link in bio → shop now';
        });
    }

    public function test_instagram_publisher_posts_first_comment_after_media_publish(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/media_publish')) {
                return Http::response(['id' => 'ig-media-9'], 200);
            }

            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?? '', '/media') || str_contains($url, '/media?')) {
                return Http::response(['id' => 'container-1'], 200);
            }

            if (str_contains($url, '/comments')) {
                return Http::response(['id' => 'ig-comment-1'], 200);
            }

            return Http::response(['error' => ['message' => 'Unexpected: '.$url]], 500);
        });

        $account = SocialAccount::factory()->forProvider('instagram')->create([
            'external_id' => 'ig-1',
            'meta' => ['instagram_account_id' => 'ig-1'],
        ]);
        $account->setAccessToken('ig-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'instagram',
            'content' => 'New drop',
            'first_comment' => 'Tap the link for sizes',
        ]);

        $result = app(InstagramPublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/photo.jpg']
        );

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('ig-media-9', $result->providerPostId);
        $this->assertSame('ig-comment-1', $result->meta['first_comment_id'] ?? null);
    }

    public function test_publish_job_still_succeeds_when_first_comment_fails(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => 'page_123_456'], 200),
            'graph.facebook.com/*/comments' => Http::response([
                'error' => ['message' => 'Comment rate limited'],
            ], 400),
        ]);

        $company = Company::factory()->create();

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
            'external_id' => 'page_123',
            'meta' => ['page_id' => 'page_123'],
        ]);
        $account->setAccessToken('page-token');
        $account->save();

        $post = SocialPost::factory()->scheduled()->create([
            'company_id' => $company->id,
            'scheduled_at' => now()->subMinute(),
        ]);

        SocialPostVersion::factory()->create([
            'social_post_id' => $post->id,
            'provider' => 'default',
            'content' => 'Hello',
            'first_comment' => 'First!',
        ]);

        $pivot = SocialPostAccount::factory()->create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => 'pending',
        ]);

        (new PublishSocialPostJob($post->id))->handle(app(\Modules\Social\Services\SocialPostPublishService::class));

        $post->refresh();
        $pivot->refresh();

        $this->assertSame('published', $post->status);
        $this->assertSame('published', $pivot->status);
        $this->assertSame('page_123_456', $pivot->provider_post_id);
    }

    public function test_facebook_publisher_skips_comment_when_empty(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => 'page_123_456'], 200),
        ]);

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'external_id' => 'page_123',
            'meta' => ['page_id' => 'page_123'],
        ]);
        $account->setAccessToken('page-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'facebook',
            'content' => 'No comment',
            'first_comment' => null,
        ]);

        $result = app(FacebookPublisher::class)->publish($account, $version, []);

        $this->assertTrue($result->success);
        $this->assertArrayNotHasKey('first_comment_id', $result->meta);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/comments'));
    }
}
