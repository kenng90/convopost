<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialFacebookConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.facebook.enabled' => true,
            'social.providers.facebook.oauth.client_id' => 'fb-client-id',
            'social.providers.facebook.oauth.client_secret' => 'fb-client-secret',
            'social.providers.facebook.oauth.redirect' => 'https://example.test/social/accounts/connect/facebook/callback',
            'social.providers.facebook.oauth.graph_version' => 'v21.0',
        ]);
    }

    public function test_facebook_connect_callback_stores_page_accounts(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-user-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'long-user-token', 'expires_in' => 5184000], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'page-111',
                        'name' => 'Acme Page',
                        'access_token' => 'page-token-111',
                        'category' => 'Brand',
                        'picture' => ['data' => ['url' => 'https://cdn.example/page.png']],
                    ],
                    [
                        'id' => 'page-222',
                        'name' => 'Acme Store',
                        'access_token' => 'page-token-222',
                        'category' => 'Shopping',
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-oauth-state-token';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.facebook.oauth_state' => $state,
                'social.facebook.company_id' => $company->id,
            ])
            ->get(route('social.accounts.connect.facebook.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $this->assertSame(2, SocialAccount::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $page = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('external_id', 'page-111')
            ->first();

        $this->assertNotNull($page);
        $this->assertSame('facebook', $page->provider);
        $this->assertSame('Acme Page', $page->name);
        $this->assertSame('active', $page->status);
        $this->assertSame('page-token-111', $page->getAccessToken());
    }

    public function test_facebook_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.facebook.oauth.client_id' => '',
            'social.providers.facebook.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.facebook'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }
}
