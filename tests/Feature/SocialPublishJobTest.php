<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Jobs\PublishSocialPostJob;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Tests\TestCase;

class SocialPublishJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_job_marks_post_and_account_published_on_success(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => 'page_123_456'], 200),
        ]);

        [$post, $pivot] = $this->makeScheduledFacebookPost();

        (new PublishSocialPostJob($post->id))->handle(app(\Modules\Social\Services\SocialPostPublishService::class));

        $post->refresh();
        $pivot->refresh();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame('published', $pivot->status);
        $this->assertSame('page_123_456', $pivot->provider_post_id);
        $this->assertNull($pivot->error);
    }

    public function test_publish_job_marks_failed_when_provider_errors(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.'],
            ], 400),
        ]);

        [$post, $pivot] = $this->makeScheduledFacebookPost();

        (new PublishSocialPostJob($post->id))->handle(app(\Modules\Social\Services\SocialPostPublishService::class));

        $post->refresh();
        $pivot->refresh();

        $this->assertSame('failed', $post->status);
        $this->assertSame('failed', $pivot->status);
        $this->assertSame('Invalid OAuth access token.', $pivot->error);
        $this->assertNull($pivot->provider_post_id);
    }

    /**
     * @return array{0: SocialPost, 1: SocialPostAccount}
     */
    protected function makeScheduledFacebookPost(): array
    {
        $company = Company::factory()->create();

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $company->id,
            'external_id' => 'page_123',
            'meta' => ['page_id' => 'page_123'],
        ]);
        $account->setAccessToken('page-token');
        $account->save();

        $post = SocialPost::factory()->scheduled()->withDefaultVersion('Hello world')->create([
            'company_id' => $company->id,
            'scheduled_at' => now()->subMinute(),
        ]);

        $pivot = SocialPostAccount::factory()->create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => 'pending',
        ]);

        return [$post, $pivot];
    }
}
