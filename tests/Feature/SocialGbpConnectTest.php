<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\GbpPublisher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialGbpConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.gbp.enabled' => true,
            'social.providers.gbp.oauth.client_id' => 'gbp-client-id',
            'social.providers.gbp.oauth.client_secret' => 'gbp-client-secret',
            'social.providers.gbp.oauth.redirect' => 'https://example.test/social/accounts/connect/gbp/callback',
            'social.providers.gbp.oauth.authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'social.providers.gbp.oauth.token_url' => 'https://oauth2.googleapis.com/token',
            'social.providers.gbp.oauth.account_management_base' => 'https://mybusinessaccountmanagement.googleapis.com',
            'social.providers.gbp.oauth.business_info_base' => 'https://mybusinessbusinessinformation.googleapis.com',
            'social.providers.gbp.oauth.local_posts_base' => 'https://mybusiness.googleapis.com',
        ]);
    }

    public function test_gbp_connect_callback_stores_location_accounts(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'gbp-access-token',
                'refresh_token' => 'gbp-refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/business.manage',
            ], 200),
            'mybusinessaccountmanagement.googleapis.com/v1/accounts' => Http::response([
                'accounts' => [
                    ['name' => 'accounts/123'],
                ],
            ], 200),
            'mybusinessbusinessinformation.googleapis.com/v1/accounts/123/locations*' => Http::response([
                'locations' => [
                    [
                        'name' => 'accounts/123/locations/456',
                        'title' => 'Acme Nairobi',
                    ],
                    [
                        'name' => 'accounts/123/locations/789',
                        'title' => 'Acme Mombasa',
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-gbp-oauth-state';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.gbp.oauth_state' => $state,
                'social.gbp.company_id' => $company->id,
            ])
            ->get(route('social.accounts.connect.gbp.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $this->assertSame(2, SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'gbp')
            ->count());

        $location = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('external_id', 'accounts_123_locations_456')
            ->first();

        $this->assertNotNull($location);
        $this->assertSame('Acme Nairobi', $location->name);
        $this->assertSame('gbp-access-token', $location->getAccessToken());
        $this->assertSame('accounts/123/locations/456', data_get($location->meta, 'location_name'));
    }

    public function test_gbp_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.gbp.oauth.client_id' => '',
            'social.providers.gbp.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.gbp'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }

    public function test_gbp_publisher_creates_local_post(): void
    {
        Http::fake([
            'mybusiness.googleapis.com/v4/accounts/123/locations/456/localPosts' => Http::response([
                'name' => 'accounts/123/locations/456/localPosts/999',
                'summary' => 'Open late tonight',
            ], 200),
        ]);

        $account = SocialAccount::factory()->forProvider('gbp')->create([
            'external_id' => 'accounts_123_locations_456',
            'meta' => [
                'location_name' => 'accounts/123/locations/456',
            ],
        ]);
        $account->setAccessToken('gbp-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'gbp',
            'content' => 'Open late tonight',
        ]);

        $result = app(GbpPublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/store.jpg']
        );

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('accounts/123/locations/456/localPosts/999', $result->providerPostId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/localPosts')
                && data_get($request->data(), 'summary') === 'Open late tonight'
                && data_get($request->data(), 'media.0.sourceUrl') === 'https://cdn.example/store.jpg'
                && data_get($request->data(), 'topicType') === 'STANDARD';
        });
    }

    public function test_gbp_publisher_requires_caption(): void
    {
        $account = SocialAccount::factory()->forProvider('gbp')->create([
            'meta' => ['location_name' => 'accounts/123/locations/456'],
        ]);
        $account->setAccessToken('gbp-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'gbp',
            'content' => '',
        ]);

        $result = app(GbpPublisher::class)->publish($account, $version, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('require caption', (string) $result->error);
    }
}
