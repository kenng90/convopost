<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogCategoryNormalizer;
use PHPUnit\Framework\TestCase;

class CatalogCategoryNormalizerTest extends TestCase
{
    public function test_it_normalizes_category_strings(): void
    {
        $normalizer = new CatalogCategoryNormalizer;

        $this->assertSame('Pet Supplies', $normalizer->normalizeCategory('  pet   supplies '));
        $this->assertSame('', $normalizer->normalizeCategory(''));
    }

    public function test_it_normalizes_items_array(): void
    {
        $normalizer = new CatalogCategoryNormalizer;

        $items = $normalizer->normalizeItems([
            ['id' => '1', 'title' => 'A', 'category' => 'dog food'],
            ['id' => '2', 'title' => 'B', 'category' => 'Dog Food'],
        ]);

        $this->assertSame('Dog Food', $items[0]['category']);
        $this->assertSame('Dog Food', $items[1]['category']);
    }
}
