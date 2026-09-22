<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostClick;
use Modules\Social\Services\SocialOfferTrackingService;
use Tests\TestCase;

class SocialOfferAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_click_sets_session_and_attributes_invoice_at_checkout(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Social Shop',
            'slug' => 'social-shop',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'sku-social',
            'title' => 'Social Product',
            'price' => 25,
            'quantityAvailable' => 5,
        ]]);

        $post = SocialPost::factory()->create([
            'company_id' => $company->id,
        ]);

        $offer = SocialOfferLink::factory()->create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'url' => route('catalog.public', $catalog->id, false),
            'click_count' => 0,
        ]);

        $this->get(route('social.offer.redirect', ['token' => $offer->tracking_token]))
            ->assertRedirect()
            ->assertSessionHas(SocialOfferTrackingService::SESSION_KEY, function (array $payload) use ($post, $offer) {
                return (int) ($payload['social_post_id'] ?? 0) === $post->id
                    && (int) ($payload['social_offer_link_id'] ?? 0) === $offer->id;
            });

        $this->assertDatabaseHas('social_post_clicks', [
            'social_offer_link_id' => $offer->id,
            'social_post_id' => $post->id,
            'company_id' => $company->id,
        ]);

        $this->assertSame(1, SocialPostClick::query()->count());
        $this->assertSame(
            1,
            (int) SocialOfferLink::withoutGlobalScopes()->whereKey($offer->id)->value('click_count')
        );

        $response = $this->postJson(route('catalog.create-invoice', $catalog->id), [
            'items' => [['id' => 'sku-social', 'quantity' => 1]],
            'customerPhone' => '254712345678',
            'deliveryAddress' => 'Kilimani, Nairobi',
            'amount' => 25,
        ]);

        $response->assertOk();
        $response->assertSessionMissing(SocialOfferTrackingService::SESSION_KEY);

        $invoice = Invoice::query()->where('catalog_id', $catalog->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame($post->id, $invoice->social_post_id);
        $this->assertSame($offer->id, $invoice->social_offer_link_id);
    }
}
