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
}
