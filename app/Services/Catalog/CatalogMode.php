<?php

namespace App\Services\Catalog;

final class CatalogMode
{
    public const COMMERCE = 'commerce';

    public const LISTING = 'listing';

    public const SERVICE = 'service';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::COMMERCE,
            self::LISTING,
            self::SERVICE,
        ];
    }
}
