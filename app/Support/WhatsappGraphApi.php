<?php

namespace App\Support;

class WhatsappGraphApi
{
    public static function version(): string
    {
        return (string) config('whatsapp-flows.graph_api_version', 'v19.0');
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('whatsapp-flows.graph_api_base_url', 'https://graph.facebook.com'), '/');
    }

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return self::baseUrl().'/'.self::version().($path !== '' ? '/'.$path : '');
    }
}
