<?php

namespace Modules\Wpsupportlanding\Support;

class ChatDukaBrand
{
    public static function name(): string
    {
        return 'ChatDuka';
    }

    public static function tagline(): string
    {
        return 'Your duka, in every chat.';
    }

    public static function description(): string
    {
        return 'Sell, support, and get paid on WhatsApp, Instagram & Messenger. Recover carts, convert bookings, and collect unpaid invoices with M-Pesa — one platform for the modern duka.';
    }

    public static function metaTitle(): string
    {
        return self::name().' — '.self::tagline();
    }

    public static function supportEmail(): string
    {
        return (string) config('settings.contact_email', 'hello@chatduka.com');
    }

    public static function markUrl(): string
    {
        return asset('landing/chatduka/mark.png');
    }

    public static function logoUrl(): string
    {
        return asset('landing/chatduka/logo.png');
    }

    public static function logoOnDarkUrl(): string
    {
        return asset('landing/chatduka/logo-on-dark.png');
    }

    public static function ogImageUrl(): string
    {
        return asset('landing/chatduka/og.png');
    }

    public static function faviconVersion(): string
    {
        $path = public_path('landing/chatduka/favicon.ico');

        return (string) (@filemtime($path) ?: '1');
    }
}
