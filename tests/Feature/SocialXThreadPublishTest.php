<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\XPublisher;
use Modules\Social\Support\XThreadParts;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialXThreadPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);
        config([
            'social.providers.x.oauth.api_base' => 'https://api.twitter.com',
            'social.providers.x.oauth.upload_base' => 'https://upload.twitter.com',
        ]);
    }

    public function test_x_publisher_posts_reply_chain_for_thread(): void
    {
        $calls = [];

        Http::fake(function ($request) use (&$calls) {
            if (! str_contains($request->url(), '/2/tweets')) {
                return Http::response(['error' => 'unexpected'], 500);
            }

            $calls[] = $request->data();
            $id = 'tweet-'.(count($calls));

            return Http::response(['data' => ['id' => $id]], 201);
        });

        $account = SocialAccount::factory()->forProvider('x')->create();
        $account->setAccessToken('x-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'x',
            'content' => 'Tweet one',
            'provider_payload' => [
                'thread' => ['Tweet two', 'Tweet three'],
            ],
        ]);

        $result = app(XPublisher::class)->publish($account, $version, []);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('tweet-1', $result->providerPostId);
        $this->assertSame(['tweet-1', 'tweet-2', 'tweet-3'], $result->meta['thread_ids'] ?? null);
        $this->assertCount(3, $calls);
        $this->assertSame('Tweet one', data_get($calls[0], 'text'));
        $this->assertNull(data_get($calls[0], 'reply'));
        $this->assertSame('tweet-1', data_get($calls[1], 'reply.in_reply_to_tweet_id'));
        $this->assertSame('Tweet two', data_get($calls[1], 'text'));
        $this->assertSame('tweet-2', data_get($calls[2], 'reply.in_reply_to_tweet_id'));
    }

    public function test_composer_stores_thread_replies_on_version_payload(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $account = SocialAccount::factory()->forProvider('x')->create([
            'company_id' => $company->id,
        ]);

        $this->actingAs($owner)->withSession(['company_id' => $company->id]);

        Livewire::test(PostComposer::class)
            ->set('content', 'Root tweet')
            ->set('selectedAccountIds', [$account->id])
            ->set('xThreadReplies', ['Second tweet', 'Third tweet'])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $post = SocialPost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($post);

        $version = $post->versions()->where('provider', 'default')->first();
        $this->assertNotNull($version);
        $this->assertSame(['Second tweet', 'Third tweet'], data_get($version->provider_payload, 'thread'));
        $this->assertSame(['Root tweet', 'Second tweet', 'Third tweet'], XThreadParts::fromVersion($version));
    }

    public function test_x_publisher_single_tweet_without_thread_payload(): void
    {
        Http::fake([
            'api.twitter.com/2/tweets' => Http::response(['data' => ['id' => 'solo-1']], 201),
        ]);

        $account = SocialAccount::factory()->forProvider('x')->create();
        $account->setAccessToken('x-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'x',
            'content' => 'Just one',
            'provider_payload' => [],
        ]);

        $result = app(XPublisher::class)->publish($account, $version, []);

        $this->assertTrue($result->success);
        $this->assertSame('solo-1', $result->providerPostId);
        $this->assertSame(['solo-1'], $result->meta['thread_ids'] ?? null);
    }
}
