<?php

namespace Modules\Wpsupportlanding\Support;

class MauzoChatBrand
{
    public static function name(): string
    {
        return 'MauzoChat';
    }

    public static function tagline(): string
    {
        return 'Connect. Chat. Close More Sales.';
    }

    public static function description(): string
    {
        return 'Sell, support, and get paid on WhatsApp, Instagram & Messenger. Recover carts, convert bookings, and collect unpaid invoices with M-Pesa — one platform to connect, chat, and close more sales.';
    }

    public static function metaTitle(): string
    {
        return self::name().' — '.self::tagline();
    }

    public static function supportEmail(): string
    {
        return (string) config('settings.contact_email', 'hello@mauzochat.com');
    }

    public static function markUrl(): string
    {
        return asset('landing/mauzochat/mark.png');
    }

    public static function logoUrl(): string
    {
        return asset('landing/mauzochat/logo.png');
    }

    public static function logoOnDarkUrl(): string
    {
        return asset('landing/mauzochat/logo-on-dark.png');
    }

    public static function ogImageUrl(): string
    {
        return asset('landing/mauzochat/og.png');
    }

    public static function faviconVersion(): string
    {
        $path = public_path('landing/mauzochat/favicon.ico');

        return (string) (@filemtime($path) ?: '1');
    }
}
