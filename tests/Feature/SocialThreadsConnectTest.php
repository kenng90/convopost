<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\ThreadsPublisher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialThreadsConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.threads.enabled' => true,
            'social.providers.threads.oauth.client_id' => 'threads-client-id',
            'social.providers.threads.oauth.client_secret' => 'threads-client-secret',
            'social.providers.threads.oauth.redirect' => 'https://example.test/social/accounts/connect/threads/callback',
            'social.providers.threads.oauth.authorize_url' => 'https://threads.net/oauth/authorize',
            'social.providers.threads.oauth.api_base' => 'https://graph.threads.net',
            'social.providers.threads.oauth.graph_version' => 'v1.0',
        ]);
    }

    public function test_threads_connect_callback_stores_account(): void
    {
        Http::fake([
            'graph.threads.net/oauth/access_token*' => Http::response([
                'access_token' => 'th-short-token',
                'user_id' => 111,
            ], 200),
            'graph.threads.net/access_token*' => Http::response([
                'access_token' => 'th-long-token',
                'expires_in' => 5184000,
            ], 200),
            'graph.threads.net/v1.0/me*' => Http::response([
                'id' => '111',
                'username' => 'acmethreads',
                'name' => 'Acme Threads',
                'threads_profile_picture_url' => 'https://cdn.example/th.png',
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-threads-oauth-state';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.threads.oauth_state' => $state,
                'social.threads.company_id' => $company->id,
            ])
            ->get(route('social.accounts.connect.threads.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $account = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'threads')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('111', $account->external_id);
        $this->assertSame('Acme Threads', $account->name);
        $this->assertSame('acmethreads', $account->username);
        $this->assertSame('active', $account->status);
        $this->assertSame('th-long-token', $account->getAccessToken());
    }

    public function test_threads_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.threads.oauth.client_id' => '',
            'social.providers.threads.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.threads'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }

    public function test_threads_publisher_creates_and_publishes_text_post(): void
    {
        Http::fake([
            'graph.threads.net/*/threads' => Http::response(['id' => 'container-1'], 200),
            'graph.threads.net/*/threads_publish' => Http::response(['id' => 'thread-post-9'], 200),
        ]);

        $account = SocialAccount::factory()->forProvider('threads')->create([
            'external_id' => '111',
            'meta' => ['threads_user_id' => '111'],
        ]);
        $account->setAccessToken('th-long-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'threads',
            'content' => 'Hello Threads',
        ]);

        $result = app(ThreadsPublisher::class)->publish($account, $version, []);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('thread-post-9', $result->providerPostId);

        Http::assertSent(function ($request) {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/threads')
                && data_get($request->data(), 'media_type') === 'TEXT'
                && data_get($request->data(), 'text') === 'Hello Threads';
        });
    }

    public function test_threads_publisher_posts_image_when_media_provided(): void
    {
        Http::fake([
            'graph.threads.net/*/threads' => Http::response(['id' => 'container-2'], 200),
            'graph.threads.net/*/threads_publish' => Http::response(['id' => 'thread-post-10'], 200),
        ]);

        $account = SocialAccount::factory()->forProvider('threads')->create([
            'external_id' => '111',
        ]);
        $account->setAccessToken('th-long-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'threads',
            'content' => 'With image',
        ]);

        $result = app(ThreadsPublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/photo.jpg']
        );

        $this->assertTrue($result->success, (string) $result->error);

        Http::assertSent(function ($request) {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/threads')
                && data_get($request->data(), 'media_type') === 'IMAGE'
                && data_get($request->data(), 'image_url') === 'https://cdn.example/photo.jpg';
        });
    }
}
