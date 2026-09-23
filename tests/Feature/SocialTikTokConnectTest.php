<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\TikTokPublisher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialTikTokConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.tiktok.enabled' => true,
            'social.providers.tiktok.oauth.client_key' => 'tt-client-key',
            'social.providers.tiktok.oauth.client_secret' => 'tt-client-secret',
            'social.providers.tiktok.oauth.redirect' => 'https://example.test/social/accounts/connect/tiktok/callback',
            'social.providers.tiktok.oauth.authorize_url' => 'https://www.tiktok.com/v2/auth/authorize/',
            'social.providers.tiktok.oauth.token_url' => 'https://open.tiktokapis.com/v2/oauth/token/',
            'social.providers.tiktok.oauth.api_base' => 'https://open.tiktokapis.com',
        ]);
    }

    public function test_tiktok_connect_callback_stores_creator_account(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'tt-access-token',
                'refresh_token' => 'tt-refresh-token',
                'open_id' => 'open-id-999',
                'expires_in' => 86400,
                'scope' => 'user.info.basic,video.upload,video.publish',
            ], 200),
            'open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'open_id' => 'open-id-999',
                        'display_name' => 'Acme Creator',
                        'username' => 'acmecreator',
                        'avatar_url' => 'https://cdn.example/avatar.png',
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-tiktok-oauth-state';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.tiktok.oauth_state' => $state,
                'social.tiktok.company_id' => $company->id,
            ])
            ->get(route('social.accounts.connect.tiktok.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $account = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'tiktok')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('open-id-999', $account->external_id);
        $this->assertSame('Acme Creator', $account->name);
        $this->assertSame('acmecreator', $account->username);
        $this->assertSame('active', $account->status);
        $this->assertSame('tt-access-token', $account->getAccessToken());
        $this->assertSame('tt-refresh-token', $account->getRefreshToken());
    }

    public function test_tiktok_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.tiktok.oauth.client_key' => '',
            'social.providers.tiktok.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.tiktok'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }

    public function test_tiktok_publisher_posts_video_via_pull_from_url(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => ['publish_id' => 'v_pub_123'],
            ], 200),
        ]);

        $account = SocialAccount::factory()->forProvider('tiktok')->create([
            'external_id' => 'open-id-999',
        ]);
        $account->setAccessToken('tt-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'tiktok',
            'content' => 'Launch day clip',
            'media_ids' => [],
        ]);

        $result = app(TikTokPublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/videos/launch.mp4']
        );

        $this->assertTrue($result->success);
        $this->assertSame('v_pub_123', $result->providerPostId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/post/publish/video/init/')
                && data_get($request->data(), 'source_info.source') === 'PULL_FROM_URL'
                && data_get($request->data(), 'source_info.video_url') === 'https://cdn.example/videos/launch.mp4';
        });
    }

    public function test_tiktok_publisher_requires_media(): void
    {
        $account = SocialAccount::factory()->forProvider('tiktok')->create();
        $account->setAccessToken('tt-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'tiktok',
            'content' => 'No media',
        ]);

        $result = app(TikTokPublisher::class)->publish($account, $version, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('require at least one', (string) $result->error);
    }
}
