<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Publishing\PinterestPublisher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialPinterestConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.pinterest.enabled' => true,
            'social.providers.pinterest.oauth.client_id' => 'pin-client-id',
            'social.providers.pinterest.oauth.client_secret' => 'pin-client-secret',
            'social.providers.pinterest.oauth.redirect' => 'https://example.test/social/accounts/connect/pinterest/callback',
            'social.providers.pinterest.oauth.authorize_url' => 'https://www.pinterest.com/oauth/',
            'social.providers.pinterest.oauth.token_url' => 'https://api.pinterest.com/v5/oauth/token',
            'social.providers.pinterest.oauth.api_base' => 'https://api.pinterest.com',
        ]);
    }

    public function test_pinterest_connect_callback_stores_board_accounts(): void
    {
        Http::fake([
            'api.pinterest.com/v5/oauth/token' => Http::response([
                'access_token' => 'pin-access-token',
                'refresh_token' => 'pin-refresh-token',
                'expires_in' => 2592000,
                'scope' => 'boards:read,boards:write,pins:read,pins:write,user_accounts:read',
            ], 200),
            'api.pinterest.com/v5/user_account' => Http::response([
                'username' => 'acmebrand',
                'profile_image' => 'https://cdn.example/pin.png',
            ], 200),
            'api.pinterest.com/v5/boards*' => Http::response([
                'items' => [
                    ['id' => 'board-1', 'name' => 'Products'],
                    ['id' => 'board-2', 'name' => 'Inspiration'],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $state = 'test-pinterest-oauth-state';

        $response = $this->actingAs($owner)
            ->withSession([
                'company_id' => $company->id,
                'social.pinterest.oauth_state' => $state,
                'social.pinterest.company_id' => $company->id,
            ])
            ->get(route('social.accounts.connect.pinterest.callback', [
                'code' => 'auth-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('status');

        $this->assertSame(2, SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'pinterest')
            ->count());

        $board = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('external_id', 'board-1')
            ->first();

        $this->assertNotNull($board);
        $this->assertSame('Products', $board->name);
        $this->assertSame('acmebrand', $board->username);
        $this->assertSame('pin-access-token', $board->getAccessToken());
        $this->assertSame('pin-refresh-token', $board->getRefreshToken());
        $this->assertSame('board-1', data_get($board->meta, 'board_id'));
    }

    public function test_pinterest_connect_redirect_requires_configuration(): void
    {
        config([
            'social.providers.pinterest.oauth.client_id' => '',
            'social.providers.pinterest.oauth.client_secret' => '',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.pinterest'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }

    public function test_pinterest_publisher_creates_pin_from_image_url(): void
    {
        Http::fake([
            'api.pinterest.com/v5/pins' => Http::response([
                'id' => 'pin-999',
            ], 201),
        ]);

        $account = SocialAccount::factory()->forProvider('pinterest')->create([
            'external_id' => 'board-1',
            'meta' => ['board_id' => 'board-1'],
        ]);
        $account->setAccessToken('pin-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'pinterest',
            'content' => 'New arrival',
        ]);

        $result = app(PinterestPublisher::class)->publish(
            $account,
            $version,
            ['https://cdn.example/product.jpg']
        );

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('pin-999', $result->providerPostId);

        Http::assertSent(function ($request) {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/v5/pins')
                && data_get($request->data(), 'board_id') === 'board-1'
                && data_get($request->data(), 'media_source.source_type') === 'image_url'
                && data_get($request->data(), 'media_source.url') === 'https://cdn.example/product.jpg';
        });
    }

    public function test_pinterest_publisher_requires_image_media(): void
    {
        $account = SocialAccount::factory()->forProvider('pinterest')->create([
            'external_id' => 'board-1',
        ]);
        $account->setAccessToken('pin-access-token');
        $account->save();

        $version = new SocialPostVersion([
            'provider' => 'pinterest',
            'content' => 'No media',
        ]);

        $result = app(PinterestPublisher::class)->publish($account, $version, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('require at least one image', (string) $result->error);
    }
}
