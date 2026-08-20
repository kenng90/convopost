<?php

namespace App\Support;

use RuntimeException;

class FaviconWriter
{
    /**
     * Pack PNG files into a multi-size .ico (PNG-in-ICO).
     *
     * @param  array<int, string>  $pngPaths
     */
    public static function writeIcoFromPngs(array $pngPaths, string $destination): void
    {
        $images = [];

        foreach ($pngPaths as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $data = file_get_contents($path);
            if ($data === false) {
                continue;
            }

            $size = self::pngPixelSize($data);
            if ($size === null) {
                continue;
            }

            $images[] = [
                'width' => $size[0],
                'height' => $size[1],
                'data' => $data,
            ];
        }

        if ($images === []) {
            throw new RuntimeException('No readable PNG files to pack into favicon.ico.');
        }

        if (file_put_contents($destination, self::packIco($images)) === false) {
            throw new RuntimeException('Unable to write favicon.ico.');
        }
    }

    /**
     * @param  array<int, array{width: int, height: int, data: string}>  $images
     */
    public static function packIco(array $images): string
    {
        $count = count($images);
        $offset = 6 + (16 * $count);
        $entries = '';
        $payload = '';

        foreach ($images as $image) {
            $size = strlen($image['data']);
            $width = $image['width'] >= 256 ? 0 : $image['width'];
            $height = $image['height'] >= 256 ? 0 : $image['height'];

            $entries .= pack('C', $width);
            $entries .= pack('C', $height);
            $entries .= pack('C', 0);
            $entries .= pack('C', 0);
            $entries .= pack('v', 1);
            $entries .= pack('v', 32);
            $entries .= pack('V', $size);
            $entries .= pack('V', $offset);

            $payload .= $image['data'];
            $offset += $size;
        }

        return pack('vvv', 0, 1, $count).$entries.$payload;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public static function pngPixelSize(string $png): ?array
    {
        if (strlen($png) < 24 || ! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $width = unpack('N', substr($png, 16, 4));
        $height = unpack('N', substr($png, 20, 4));

        if ($width === false || $height === false) {
            return null;
        }

        return [$width[1], $height[1]];
    }
}
