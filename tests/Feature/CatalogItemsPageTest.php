<?php

namespace Tests\Feature;

use Tests\TestCase;

class CatalogItemsPageTest extends TestCase
{
    public function test_catalog_items_page_requires_authentication(): void
    {
        $response = $this->get('/catalogs/1/items');

        $response->assertRedirect(route('login'));
    }
}
