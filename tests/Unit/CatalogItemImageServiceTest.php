<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogItemImageService;
use Tests\TestCase;

class CatalogItemImageServiceTest extends TestCase
{
    public function test_normalize_merges_cover_and_gallery_urls(): void
    {
        $service = app(CatalogItemImageService::class);

        $images = $service->normalize([
            'imageUrl' => 'https://example.com/cover.jpg',
            'imagesText' => "https://example.com/photo-2.jpg\nhttps://example.com/photo-3.jpg",
        ]);

        $this->assertSame([
            'https://example.com/cover.jpg',
            'https://example.com/photo-2.jpg',
            'https://example.com/photo-3.jpg',
        ], $images);
        $this->assertSame('https://example.com/cover.jpg', $service->primaryUrl($images));
    }
}
