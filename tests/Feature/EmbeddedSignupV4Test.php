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

        $this->assertDatabaseHas('channel_connections', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-99',
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
}
