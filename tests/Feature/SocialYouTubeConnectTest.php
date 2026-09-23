<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\YouTubePublisher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialYouTubeConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.youtube.enabled' => true,
            'social.providers.youtube.oauth.client_id' => 'yt-client-id',
            'social.providers.youtube.oauth.client_secret' => 'yt-client-secret',
            'social.providers.youtube.oauth.redirect' => 'https://example.test/social/accounts/connect/youtube/callback',
            'social.providers.youtube.oauth.authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'social.providers.youtube.oauth.token_url' => 'https://oauth2.googleapis.com/token',
            'social.providers.youtube.oauth.api_base' => 'https://www.googleapis.com',
        ]);
    }

    public function test_youtube_connect_callback_stores_channel_account(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'yt-access-token',
                'refresh_token' => 'yt-refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
            ], 200),
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UC-channel-123',
                        'snippet' => [
                            'title' => 'Acme Channel',
                            'customUrl' => '@acmechannel',
                            'thumbnails' => [
                                'default' => ['url' => 'https://cdn.example/yt.png'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-youtube-oauth-state';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.youtube.oauth_state' => $state,
                'social.youtube.company_id' => $company->id,
            ])
            ->get(route('social.accounts.connect.youtube.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $account = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'youtube')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('UC-channel-123', $account->external_id);
        $this->assertSame('Acme Channel', $account->name);
        $this->assertSame('acmechannel', $account->username);
        $this->assertSame('active', $account->status);
        $this->assertSame('yt-access-token', $account->getAccessToken());
        $this->assertSame('yt-refresh-token', $account->getRefreshToken());
    }

    public function test_youtube_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.youtube.oauth.client_id' => '',
            'social.providers.youtube.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.youtube'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }

    public function test_youtube_publisher_uploads_short_via_resumable_api(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'cdn.example/videos/short.mp4')) {
                return Http::response('fake-video-bytes', 200, [
                    'Content-Type' => 'video/mp4',
                ]);
            }

            if (str_contains($url, 'uploadType=resumable')) {
                return Http::response('', 200, [
                    'Location' => 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=abc',
                ]);
            }

            if (str_contains($url, 'upload_id=abc')) {
                return Http::response([
                    'id' => 'yt_video_999',
                    'snippet' => ['title' => 'Launch day'],
                ], 200);
            }

            return Http::response(['error' => ['message' => 'Unexpected URL: '.$url]], 500);
        });

        $account = SocialAccount::factory()->forProvider('youtube')->create([
            'external_id' => 'UC-channel-123',
        ]);
        $account->setAccessToken('yt-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'youtube',
            'content' => 'Launch day',
            'media_ids' => [],
        ]);

        $result = app(YouTubePublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/videos/short.mp4']
        );

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('yt_video_999', $result->providerPostId);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'uploadType=resumable')) {
                return false;
            }

            $data = $request->data();

            return data_get($data, 'snippet.title') === 'Launch day'
                && str_contains((string) data_get($data, 'snippet.description'), '#Shorts');
        });
    }

    public function test_youtube_publisher_requires_video_media(): void
    {
        $account = SocialAccount::factory()->forProvider('youtube')->create();
        $account->setAccessToken('yt-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'youtube',
            'content' => 'No media',
        ]);

        $result = app(YouTubePublisher::class)->publish($account, $version, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('require a video', (string) $result->error);
    }
}
