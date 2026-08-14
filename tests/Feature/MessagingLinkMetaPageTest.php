<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingLinkMetaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_page_and_instagram_from_graph_payload(): void
    {
        Http::fake([
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/1072030281944265*' => Http::response([
                'id' => '1072030281944265',
                'name' => 'Client Page',
                'access_token' => 'page-token',
                'instagram_business_account' => [
                    'id' => '17841401947499512',
                    'username' => 'clientig',
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $company->setConfig('whatsapp_permanent_access_token', 'user-token');
        $company->setConfig('plain_token', 'hook-token');

        $this->artisan('messaging:link-meta-page', [
            'company' => $company->id,
            '--page' => '1072030281944265',
            '--instagram' => '17841401947499512',
        ])->assertSuccessful();

        $this->assertDatabaseHas('channel_connections', [
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => '1072030281944265',
        ]);

        $instagram = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Instagram->value)
            ->first();

        $this->assertSame('17841401947499512', $instagram?->credential('instagram_account_id'));
        $this->assertSame('page-token', $instagram?->accessToken());
        $this->assertSame('yes', $company->fresh()->getConfig('instagram_connected'));
        $this->assertSame('1072030281944265', $company->fresh()->getConfig('messenger_page_id'));
    }
}
