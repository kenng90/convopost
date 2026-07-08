<?php

namespace App\Support;

class ConvoConnectBrand
{
    public static function name(): string
    {
        return (string) config('hostpinnacle.brand_name', 'ConvoConnect');
    }

    public static function displayProvider(?string $provider): string
    {
        if ($provider === 'hostpinnacle') {
            return 'convoconnect';
        }

        return (string) $provider;
    }

    public static function sanitizeError(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        return str_ireplace(['hostpinnacle'], static::name(), $message);
    }
}
