<?php

namespace App\Services\Security;

use RuntimeException;
use ZipArchive;

class SecureZipExtractor
{
    /**
     * Extract a zip archive while rejecting path traversal and absolute paths.
     *
     * @throws RuntimeException
     */
    public function extract(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open archive.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || $name === '') {
                    throw new RuntimeException('Archive contains an invalid entry.');
                }

                $normalized = str_replace('\\', '/', $name);

                if (str_contains($normalized, "\0")
                    || str_starts_with($normalized, '/')
                    || str_contains($normalized, '../')
                    || $normalized === '..'
                    || str_starts_with($normalized, '../')
                ) {
                    throw new RuntimeException('Archive contains an unsafe path.');
                }
            }

            if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
                throw new RuntimeException('Unable to create extract directory.');
            }

            if (! $zip->extractTo($destination)) {
                throw new RuntimeException('Unable to extract archive.');
            }
        } finally {
            $zip->close();
        }
    }
}
