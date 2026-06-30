<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogCheckoutPendingService;
use App\Services\Catalog\CatalogItemRepository;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Tests\TestCase;

class CatalogCheckoutPendingServiceTest extends TestCase
{
    public function test_store_pending_sets_flag_without_clearing_current_node(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop-pending',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'sku-1',
            'title' => 'SKU One',
            'price' => 10,
            'quantityAvailable' => 5,
        ]]);

        $catalog->refresh();

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254700000099',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $orderMessage = "📦 *New Order from Catalog: {$catalog->name}*\n\n📋 *Items:*\n• SKU One (x1) - KES 10.00\n\n💰 *Total:* KES 10.00";

        app(CatalogCheckoutPendingService::class)->storePending(
            $contact,
            $flow->id,
            'catalog-1',
            $catalog,
            [['id' => 'sku-1', 'quantity' => 1]],
            $orderMessage
        );

        $this->assertSame('1', $contact->getContactStateValue($flow->id, CatalogCheckoutPendingService::PENDING_FLAG));
        $this->assertSame($orderMessage, $contact->getContactStateValue($flow->id, CatalogCheckoutPendingService::PENDING_MESSAGE));
        $this->assertSame('catalog-1', $contact->getContactStateValue($flow->id, 'current_node'));
    }

    public function test_is_order_confirmation_matches_pending_message_text(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254700000100',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $pendingMessage = "📦 *New Order from Catalog: Shop*\n\n📋 *Items:*\n• Widget (x1)";

        $contact->setContactState($flow->id, CatalogCheckoutPendingService::PENDING_FLAG, '1');
        $contact->setContactState($flow->id, CatalogCheckoutPendingService::PENDING_MESSAGE, $pendingMessage);

        $service = app(CatalogCheckoutPendingService::class);

        $this->assertTrue($service->isOrderConfirmationMessage($contact, $flow->id, $pendingMessage));
        $this->assertTrue($service->isOrderConfirmationMessage($contact, $flow->id, "  {$pendingMessage}  "));
        $this->assertFalse($service->isOrderConfirmationMessage($contact, $flow->id, 'Hello there'));
    }
}
