<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\XPublisher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialXConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.x.enabled' => true,
            'social.providers.x.oauth.client_id' => 'x-client-id',
            'social.providers.x.oauth.client_secret' => 'x-client-secret',
            'social.providers.x.oauth.redirect' => 'https://example.test/social/accounts/connect/x/callback',
            'social.providers.x.oauth.authorize_url' => 'https://twitter.com/i/oauth2/authorize',
            'social.providers.x.oauth.token_url' => 'https://api.twitter.com/2/oauth2/token',
            'social.providers.x.oauth.api_base' => 'https://api.twitter.com',
            'social.providers.x.oauth.upload_base' => 'https://upload.twitter.com',
        ]);
    }

    public function test_x_connect_callback_stores_account(): void
    {
        Http::fake([
            'api.twitter.com/2/oauth2/token' => Http::response([
                'access_token' => 'x-access-token',
                'refresh_token' => 'x-refresh-token',
                'expires_in' => 7200,
                'scope' => 'tweet.read tweet.write users.read offline.access',
            ], 200),
            'api.twitter.com/2/users/me*' => Http::response([
                'data' => [
                    'id' => '42',
                    'name' => 'Acme Brand',
                    'username' => 'acmebrand',
                    'profile_image_url' => 'https://cdn.example/x.png',
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-x-oauth-state';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.x.oauth_state' => $state,
                'social.x.company_id' => $company->id,
                'social.x.code_verifier' => 'test-code-verifier',
            ])
            ->get(route('social.accounts.connect.x.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $account = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'x')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('42', $account->external_id);
        $this->assertSame('Acme Brand', $account->name);
        $this->assertSame('acmebrand', $account->username);
        $this->assertSame('x-access-token', $account->getAccessToken());
        $this->assertSame('x-refresh-token', $account->getRefreshToken());
    }

    public function test_x_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.x.oauth.client_id' => '',
            'social.providers.x.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.x'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }

    public function test_x_publisher_creates_text_tweet(): void
    {
        Http::fake([
            'api.twitter.com/2/tweets' => Http::response([
                'data' => ['id' => 'tweet-123', 'text' => 'Hello X'],
            ], 201),
        ]);

        $account = SocialAccount::factory()->forProvider('x')->create([
            'external_id' => '42',
        ]);
        $account->setAccessToken('x-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'x',
            'content' => 'Hello X',
        ]);

        $result = app(XPublisher::class)->publish($account, $version, []);

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('tweet-123', $result->providerPostId);

        Http::assertSent(function ($request) {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/2/tweets')
                && data_get($request->data(), 'text') === 'Hello X';
        });
    }

    public function test_x_publisher_uploads_media_then_tweets(): void
    {
        Http::fake([
            'cdn.example/photo.jpg' => Http::response('fake-image-bytes', 200),
            'upload.twitter.com/1.1/media/upload.json' => Http::response([
                'media_id_string' => 'media-77',
            ], 200),
            'api.twitter.com/2/tweets' => Http::response([
                'data' => ['id' => 'tweet-456'],
            ], 201),
        ]);

        $account = SocialAccount::factory()->forProvider('x')->create();
        $account->setAccessToken('x-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'x',
            'content' => 'With photo',
        ]);

        $result = app(XPublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/photo.jpg']
        );

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('tweet-456', $result->providerPostId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/2/tweets')
                && data_get($request->data(), 'media.media_ids.0') === 'media-77';
        });
    }
}
