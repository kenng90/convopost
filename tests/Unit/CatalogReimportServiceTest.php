<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogCategoryNormalizer;
use App\Services\Catalog\CatalogReimportService;
use Tests\TestCase;

class CatalogReimportServiceTest extends TestCase
{
    public function test_merge_by_item_id_adds_and_updates_rows(): void
    {
        $service = new CatalogReimportService(new CatalogCategoryNormalizer);

        $existing = [
            ['id' => 'A', 'title' => 'Old', 'price' => 10],
            ['id' => 'B', 'title' => 'Keep', 'price' => 5],
        ];

        $imported = [
            ['id' => 'A', 'title' => 'New', 'price' => 12],
            ['id' => 'C', 'title' => 'Added', 'price' => 1],
        ];

        $result = $service->mergeByItemId($existing, $imported);

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['updated']);
        $this->assertCount(3, $result['items']);
        $this->assertSame('New', collect($result['items'])->firstWhere('id', 'A')['title']);
    }

    public function test_preview_merge_reports_counts(): void
    {
        $service = new CatalogReimportService(new CatalogCategoryNormalizer);

        $preview = $service->previewMerge(
            [['id' => 'A', 'title' => 'A'], ['id' => 'B', 'title' => 'B']],
            [['id' => 'A', 'title' => 'A2'], ['id' => 'C', 'title' => 'C']]
        );

        $this->assertSame(1, $preview['to_add']);
        $this->assertSame(1, $preview['to_update']);
        $this->assertSame(1, $preview['to_remove']);
        $this->assertSame(3, $preview['resulting_count']);
    }
}
