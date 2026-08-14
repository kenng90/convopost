<?php

namespace Tests\Unit;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\MetaWebhookConnectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MetaWebhookConnectionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_page_and_instagram_ids_from_payload(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => 'ig-biz-1',
                    'messaging' => [
                        ['recipient' => ['id' => 'ig-biz-1']],
                    ],
                    'changes' => [
                        ['value' => ['recipient' => ['id' => 'ig-biz-1']]],
                    ],
                ],
            ],
        ]);

        $ids = app(MetaWebhookConnectionResolver::class)->extractAssetIds($request);

        $this->assertSame(['ig-biz-1'], $ids);
    }

    public function test_extracts_asset_ids_from_standby_events(): void
    {
        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '17841401947499512',
                    'standby' => [
                        ['recipient' => ['id' => '17841401947499512']],
                    ],
                ],
            ],
        ]);

        $ids = app(MetaWebhookConnectionResolver::class)->extractAssetIds($request);

        $this->assertSame(['17841401947499512'], $ids);
    }

    public function test_finds_instagram_connection_by_credential_account_id(): void
    {
        $company = Company::factory()->create();
        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-1',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'page_id' => 'page-1',
                'instagram_account_id' => 'ig-9',
            ],
        ]);

        $found = app(MetaWebhookConnectionResolver::class)->findByAssetIds(
            MessagingChannelType::Instagram,
            ['ig-9'],
        );

        $this->assertNotNull($found);
        $this->assertSame($connection->id, $found->id);
    }

    public function test_instagram_webhook_matches_messenger_page_and_provisions_instagram_connection(): void
    {
        $company = Company::factory()->create();
        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => '1072030281944265',
            'display_name' => 'Messenger',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => '1072030281944265',
            ],
            'webhook_token' => 'platform-token',
        ]);

        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '17841401947499512',
                    'messaging' => [
                        [
                            'sender' => ['id' => 'ig-user-1'],
                            'recipient' => ['id' => '1072030281944265'],
                        ],
                    ],
                ],
            ],
        ]);

        $resolved = app(MetaWebhookConnectionResolver::class)->resolve(
            $request,
            MessagingChannelType::Instagram,
            'unused-url-token',
        );

        $this->assertNotNull($resolved);
        $this->assertSame(MessagingChannelType::Instagram, $resolved->channel);
        $this->assertSame($company->id, $resolved->company_id);
        $this->assertSame('17841401947499512', $resolved->credential('instagram_account_id'));
        $this->assertSame('1072030281944265', $resolved->credential('page_id'));
    }

    public function test_instagram_webhook_matches_company_config_account_id(): void
    {
        $company = Company::factory()->create();
        $company->setConfig('instagram_page_id', '1072030281944265');
        $company->setConfig('instagram_account_id', '17841401947499512');
        $company->setConfig('instagram_page_access_token', 'page-token');

        $request = Request::create('/webhook', 'POST', [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '17841401947499512',
                    'messaging' => [
                        [
                            'sender' => ['id' => 'ig-user-1'],
                            'recipient' => ['id' => '1072030281944265'],
                        ],
                    ],
                ],
            ],
        ]);

        $resolved = app(MetaWebhookConnectionResolver::class)->resolve(
            $request,
            MessagingChannelType::Instagram,
            'unused',
        );

        $this->assertNotNull($resolved);
        $this->assertSame($company->id, $resolved->company_id);
        $this->assertSame(MessagingChannelType::Instagram, $resolved->channel);
        $this->assertSame('17841401947499512', $resolved->credential('instagram_account_id'));
    }
}
