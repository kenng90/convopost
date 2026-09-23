<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Api\PublicWebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Social\Jobs\PublishSocialPostJob;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Services\SocialPostPublishService;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class SocialPublishWebhookTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_successful_publish_dispatches_social_post_published_webhook(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => 'page_123_456'], 200),
        ]);

        [$post] = $this->makeScheduledFacebookPost();

        $dispatcher = Mockery::mock(PublicWebhookDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($companyId, $type, $data) use ($post) {
                return (int) $companyId === (int) $post->company_id
                    && $type === 'social.post.published'
                    && ($data['id'] ?? null) === $post->id
                    && ($data['status'] ?? null) === 'published'
                    && is_array($data['accounts'] ?? null)
                    && ($data['accounts'][0]['provider_post_id'] ?? null) === 'page_123_456';
            })
            ->andReturn('evt_social_published');
        $this->app->instance(PublicWebhookDispatcher::class, $dispatcher);

        (new PublishSocialPostJob($post->id))->handle(app(SocialPostPublishService::class));

        $this->assertSame('published', $post->fresh()->status);
    }

    public function test_failed_publish_dispatches_social_post_failed_webhook(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.'],
            ], 400),
        ]);

        [$post] = $this->makeScheduledFacebookPost();

        $dispatcher = Mockery::mock(PublicWebhookDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($companyId, $type, $data) use ($post) {
                return (int) $companyId === (int) $post->company_id
                    && $type === 'social.post.failed'
                    && ($data['id'] ?? null) === $post->id
                    && ($data['status'] ?? null) === 'failed'
                    && ($data['accounts'][0]['error'] ?? null) === 'Invalid OAuth access token.';
            })
            ->andReturn('evt_social_failed');
        $this->app->instance(PublicWebhookDispatcher::class, $dispatcher);

        (new PublishSocialPostJob($post->id))->handle(app(SocialPostPublishService::class));

        $this->assertSame('failed', $post->fresh()->status);
    }

    public function test_webhook_endpoint_can_subscribe_to_social_publish_events(): void
    {
        $this->createPublicApiOwner();

        $this->mock(\App\Services\Security\SafeRemoteUrl::class, function ($mock) {
            $mock->shouldReceive('isPublicHttpUrl')->andReturn(true);
        });

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhooks/social',
            'events' => ['social.post.published', 'social.post.failed'],
        ], $this->publicApiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.events.0', 'social.post.published')
            ->assertJsonPath('data.events.1', 'social.post.failed');
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

        $post = SocialPost::factory()->scheduled()->withDefaultVersion('Webhook post')->create([
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
