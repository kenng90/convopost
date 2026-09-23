<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1SocialPostsTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_social_posts_are_cursor_paginated(): void
    {
        $this->createPublicApiOwner();

        foreach (range(1, 3) as $i) {
            SocialPost::factory()->draft()->withDefaultVersion('Post '.$i)->create([
                'company_id' => $this->apiCompany->id,
                'user_id' => $this->apiUser->id,
            ]);
        }

        $response = $this->getJson('/api/v1/social/posts?limit=2', $this->publicApiHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.has_more', true);
        $this->assertCount(2, $response->json('data'));

        $next = $this->getJson(
            '/api/v1/social/posts?limit=2&cursor='.$response->json('meta.next_cursor'),
            $this->publicApiHeaders()
        );
        $next->assertOk()->assertJsonPath('meta.has_more', false);
        $this->assertCount(1, $next->json('data'));
    }

    public function test_social_post_can_be_created_as_draft(): void
    {
        $this->createPublicApiOwner();

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $this->apiCompany->id,
        ]);

        $response = $this->postJson('/api/v1/social/posts', [
            'content' => 'Hello from API',
            'account_ids' => [$account->id],
            'status' => 'draft',
            'offer_type' => 'none',
        ], $this->publicApiHeaders());

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.content', 'Hello from API')
            ->assertJsonPath('data.account_ids.0', $account->id);

        $this->assertDatabaseHas('social_posts', [
            'id' => $response->json('data.id'),
            'company_id' => $this->apiCompany->id,
            'status' => 'draft',
        ]);
    }

    public function test_social_post_can_be_created_scheduled(): void
    {
        $this->createPublicApiOwner();

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $this->apiCompany->id,
        ]);

        $when = now()->addDay()->toIso8601String();

        $response = $this->postJson('/api/v1/social/posts', [
            'content' => 'Scheduled API post',
            'account_ids' => [$account->id],
            'status' => 'scheduled',
            'scheduled_at' => $when,
            'offer_type' => 'none',
        ], $this->publicApiHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'scheduled');
        $this->assertNotNull($response->json('data.scheduled_at'));
    }

    public function test_draft_social_post_can_be_scheduled_via_action(): void
    {
        $this->createPublicApiOwner();

        $post = SocialPost::factory()->draft()->withDefaultVersion('Later')->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $this->apiUser->id,
        ]);

        $when = now()->addHours(3)->toIso8601String();

        $this->postJson('/api/v1/social/posts/'.$post->id.'/schedule', [
            'scheduled_at' => $when,
        ], $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.id', $post->id);

        $this->assertSame('scheduled', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->scheduled_at);
    }

    public function test_social_post_show_is_company_scoped(): void
    {
        $this->createPublicApiOwner();

        $post = SocialPost::factory()->draft()->withDefaultVersion('Mine')->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $this->apiUser->id,
        ]);

        $other = SocialPost::factory()->draft()->withDefaultVersion('Other')->create();

        $this->getJson('/api/v1/social/posts/'.$post->id, $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.content', 'Mine');

        $this->getJson('/api/v1/social/posts/'.$other->id, $this->publicApiHeaders())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_social_posts_can_be_filtered_by_status(): void
    {
        $this->createPublicApiOwner();

        SocialPost::factory()->draft()->withDefaultVersion('Draft')->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $this->apiUser->id,
        ]);
        SocialPost::factory()->scheduled()->withDefaultVersion('Soon')->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $this->apiUser->id,
        ]);

        $response = $this->getJson('/api/v1/social/posts?status=scheduled', $this->publicApiHeaders());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('scheduled', $response->json('data.0.status'));
        $this->assertSame('Soon', $response->json('data.0.content'));
    }

    public function test_social_post_create_validates_required_fields(): void
    {
        $this->createPublicApiOwner();

        $this->postJson('/api/v1/social/posts', [
            'status' => 'draft',
            'offer_type' => 'none',
        ], $this->publicApiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content', 'account_ids']);

        $this->postJson('/api/v1/social/posts', [
            'content' => 'Needs a time',
            'account_ids' => [1],
            'status' => 'scheduled',
            'offer_type' => 'none',
        ], $this->publicApiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_social_post_create_supports_first_comment_and_thread_replies(): void
    {
        $this->createPublicApiOwner();

        $account = SocialAccount::factory()->forProvider('x')->create([
            'company_id' => $this->apiCompany->id,
        ]);

        $response = $this->postJson('/api/v1/social/posts', [
            'content' => 'Thread root',
            'account_ids' => [$account->id],
            'status' => 'draft',
            'offer_type' => 'none',
            'first_comment' => 'Pin this',
            'x_thread_replies' => ['Reply one', 'Reply two'],
        ], $this->publicApiHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.first_comment', 'Pin this')
            ->assertJsonPath('data.content', 'Thread root');

        $post = SocialPost::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['Reply one', 'Reply two'], data_get($post->defaultVersion->provider_payload, 'thread'));
    }

    public function test_published_social_post_cannot_be_scheduled(): void
    {
        $this->createPublicApiOwner();

        $post = SocialPost::factory()->published()->withDefaultVersion('Live')->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $this->apiUser->id,
        ]);

        $this->postJson('/api/v1/social/posts/'.$post->id.'/schedule', [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ], $this->publicApiHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_status');
    }

    public function test_failed_social_post_can_be_rescheduled(): void
    {
        $this->createPublicApiOwner();

        $post = SocialPost::factory()->withDefaultVersion('Retry me')->create([
            'company_id' => $this->apiCompany->id,
            'user_id' => $this->apiUser->id,
            'status' => 'failed',
            'scheduled_at' => null,
        ]);

        $this->postJson('/api/v1/social/posts/'.$post->id.'/schedule', [
            'scheduled_at' => now()->addHours(2)->toIso8601String(),
        ], $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.id', $post->id);

        $this->assertSame('scheduled', $post->fresh()->status);
    }

    public function test_social_post_create_is_idempotent(): void
    {
        $this->createPublicApiOwner();

        $account = SocialAccount::factory()->forProvider('facebook')->create([
            'company_id' => $this->apiCompany->id,
        ]);

        $payload = [
            'content' => 'Idempotent draft',
            'account_ids' => [$account->id],
            'status' => 'draft',
            'offer_type' => 'none',
        ];

        $first = $this->postJson('/api/v1/social/posts', $payload, $this->publicApiHeaders([
            'Idempotency-Key' => 'social-post-1',
        ]))->assertCreated();

        $replay = $this->postJson('/api/v1/social/posts', $payload, $this->publicApiHeaders([
            'Idempotency-Key' => 'social-post-1',
        ]))->assertCreated();

        $replay->assertHeader('Idempotent-Replay', 'true');
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->assertSame(1, SocialPost::query()->where('company_id', $this->apiCompany->id)->count());
    }
}
