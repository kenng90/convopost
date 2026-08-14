<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Embeddedlogin\Services\EmbeddedSignupCompletionService;
use Modules\Embeddedlogin\Services\EmbeddedSignupSession;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmbeddedSignupV4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);

        config([
            'services.facebook.app_id' => 'app-id',
            'services.facebook.app_secret' => 'app-secret',
            'embeddedlogin.graph_version' => 'v22.0',
        ]);
    }

    public function test_omnichannel_completion_provisions_whatsapp_and_meta_channels(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token'], 200),
            'graph.facebook.com/*/phone_numbers*' => Http::response([
                'data' => [['id' => 'phone-1', 'display_phone_number' => '+15551234567']],
            ], 200),
            'graph.facebook.com/*/register' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/page-99*' => Http::response([
                'id' => 'page-99',
                'name' => 'Test Page',
                'access_token' => 'page-token',
                'instagram_business_account' => [
                    'id' => 'ig-88',
                    'username' => 'testig',
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $session = new EmbeddedSignupSession(
            flow: 'omnichannel',
            wabaId: 'waba-1',
            phoneNumberId: 'phone-1',
            pageId: 'page-99',
            instagramAccountId: 'ig-88',
        );

        $result = app(EmbeddedSignupCompletionService::class)->complete($owner, 'auth-code', $session);

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['connected']['whatsapp']);
        $this->assertTrue($result['connected']['instagram']);
        $this->assertTrue($result['connected']['messenger']);

        $this->assertSame('yes', $company->fresh()->getConfig('whatsapp_settings_done'));
        $this->assertSame('yes', $company->fresh()->getConfig('instagram_connected'));
        $this->assertSame('page-token', $company->fresh()->getConfig('instagram_page_access_token'));

        $this->assertDatabaseHas('channel_connections', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-99',
        ]);
    }

    public function test_omnichannel_completion_discovers_page_from_token_when_session_omits_page_id(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token'], 200),
            'graph.facebook.com/*/phone_numbers*' => Http::response([
                'data' => [['id' => 'phone-1', 'display_phone_number' => '+15551234567']],
            ], 200),
            'graph.facebook.com/*/register' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [[
                    'id' => '1072030281944265',
                    'name' => 'Discovered Page',
                    'access_token' => 'page-token',
                    'instagram_business_account' => [
                        'id' => '17841401947499512',
                        'username' => 'clientig',
                    ],
                ]],
            ], 200),
            'graph.facebook.com/*/1072030281944265*' => Http::response([
                'id' => '1072030281944265',
                'name' => 'Discovered Page',
                'access_token' => 'page-token',
                'instagram_business_account' => [
                    'id' => '17841401947499512',
                    'username' => 'clientig',
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $session = new EmbeddedSignupSession(
            flow: 'omnichannel',
            wabaId: 'waba-1',
            phoneNumberId: 'phone-1',
            pageId: null,
            instagramAccountId: null,
        );

        $result = app(EmbeddedSignupCompletionService::class)->complete($owner, 'auth-code', $session);

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['connected']['instagram']);
        $this->assertTrue($result['connected']['messenger']);
        $this->assertSame('1072030281944265', $company->fresh()->getConfig('instagram_page_id'));
        $this->assertSame('17841401947499512', $company->fresh()->getConfig('instagram_account_id'));

        $this->assertDatabaseHas('channel_connections', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => '1072030281944265',
        ]);
    }

    public function test_whatsapp_only_completion_skips_meta_channel_provisioning(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token'], 200),
            'graph.facebook.com/*/phone_numbers*' => Http::response([
                'data' => [['id' => 'phone-1', 'display_phone_number' => '+15551234567']],
            ], 200),
            'graph.facebook.com/*/register' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $session = new EmbeddedSignupSession(
            flow: 'whatsapp_only',
            wabaId: 'waba-1',
            phoneNumberId: 'phone-1',
            pageId: 'page-99',
            instagramAccountId: 'ig-88',
        );

        $result = app(EmbeddedSignupCompletionService::class)->complete($owner, 'auth-code', $session);

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['connected']['whatsapp']);
        $this->assertArrayNotHasKey('instagram', $result['connected']);

        $this->assertSame(0, ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Instagram->value)
            ->count());
    }

    public function test_setup_page_shows_omnichannel_button_when_configured_and_entitled(): void
    {
        config([
            'embeddedlogin.config_id' => 'wa-config',
            'embeddedlogin.omni_config_id' => 'omni-config',
        ]);

        $plan = \App\Models\Plans::create([
            'name' => 'Pro Test',
            'price' => 1,
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'period' => 1,
        ]);
        // null capabilities = all allowed (includes inbox_instagram / inbox_messenger)

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $this->withoutMiddleware([
            \App\Http\Middleware\EnsurePlanPlugin::class,
            \App\Http\Middleware\EnsureOwnerIsOnPROPlan::class,
            \Modules\Wpbox\Http\Middleware\CheckPlan::class,
        ]);

        $response = $this->actingAs($owner)->get(route('whatsapp.setup'));

        $response->assertOk();
        $response->assertSee(__('Connect WhatsApp + Instagram + Messenger'));
        $response->assertSee(__('WhatsApp Setup'));
    }
}
