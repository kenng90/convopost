<?php

namespace Tests\Unit;

use App\Support\FaviconWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FaviconWriterTest extends TestCase
{
    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_reads_png_pixel_size(): void
    {
        $this->assertSame([1, 1], FaviconWriter::pngPixelSize($this->tinyPng()));
        $this->assertNull(FaviconWriter::pngPixelSize('not-a-png'));
    }

    public function test_packs_png_frames_into_ico(): void
    {
        $png = $this->tinyPng();
        $ico = FaviconWriter::packIco([
            ['width' => 1, 'height' => 1, 'data' => $png],
            ['width' => 1, 'height' => 1, 'data' => $png],
        ]);

        $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));

        $this->assertSame(0, $header['reserved']);
        $this->assertSame(1, $header['type']);
        $this->assertSame(2, $header['count']);
        $this->assertStringContainsString("\x89PNG", $ico);
        $this->assertSame(6 + (16 * 2) + (strlen($png) * 2), strlen($ico));
    }

    public function test_writes_ico_from_png_files(): void
    {
        $pngPath = sys_get_temp_dir().'/favicon-writer-'.uniqid('', true).'.png';
        $icoPath = sys_get_temp_dir().'/favicon-writer-'.uniqid('', true).'.ico';

        try {
            file_put_contents($pngPath, $this->tinyPng());
            FaviconWriter::writeIcoFromPngs([$pngPath], $icoPath);

            $ico = file_get_contents($icoPath);
            $this->assertNotFalse($ico);
            $this->assertStringStartsWith("\x00\x00\x01\x00", $ico);
            $this->assertStringContainsString("\x89PNG", $ico);
        } finally {
            @unlink($pngPath);
            @unlink($icoPath);
        }
    }

    public function test_throws_when_no_png_files_are_readable(): void
    {
        $this->expectException(RuntimeException::class);

        FaviconWriter::writeIcoFromPngs(['/tmp/does-not-exist-'.uniqid('', true).'.png'], sys_get_temp_dir().'/unused.ico');
    }

    private function tinyPng(): string
    {
        return base64_decode(self::TINY_PNG, true);
    }
}
