<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\CatalogSearch;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogSearchNodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'owner']);
    }

    public function test_catalog_search_selects_single_match(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop-search',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [
            ['id' => 'sku-1', 'title' => 'Blue Mug', 'price' => 100, 'description' => 'Ceramic'],
            ['id' => 'sku-2', 'title' => 'Red Plate', 'price' => 200, 'description' => 'Porcelain'],
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254700000001',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Search Flow',
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'search-1',
                        'type' => 'catalog_search',
                        'data' => [
                            'settings' => [
                                'catalogId' => (string) $catalog->id,
                                'maxResults' => 5,
                            ],
                        ],
                    ],
                    [
                        'id' => 'msg-1',
                        'type' => 'message',
                        'data' => ['settings' => ['message' => 'Found {{selected_product}}']],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => 'search-1',
                        'target' => 'msg-1',
                        'sourceHandle' => 'onMatch',
                    ],
                ],
            ]),
        ]);

        $node = new CatalogSearch([
            'id' => 'search-1',
            'type' => 'catalog_search',
            'data' => [
                'settings' => [
                    'catalogId' => (string) $catalog->id,
                    'maxResults' => 5,
                ],
            ],
        ], []);
        $node->flow_id = $flow->id;

        $data = (object) [
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'value' => 'Blue Mug',
            'extra' => '',
        ];

        $node->isStartNode = true;
        $node->listenForReply('', $data);

        $selected = json_decode($contact->getContactStateValue($flow->id, 'selected_product'), true);
        $this->assertSame('sku-1', $selected['id'] ?? null);
    }
}
