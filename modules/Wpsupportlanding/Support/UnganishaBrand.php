<?php

namespace Modules\Wpsupportlanding\Support;

use App\Support\Offering;

class UnganishaBrand
{
    public static function name(): string
    {
        return 'Unganisha';
    }

    public static function hubName(): string
    {
        return 'Unganisha Hub';
    }

    public static function tagline(): string
    {
        return Offering::whatsappEnabled()
            ? 'Where conversations become commerce.'
            : 'Publish social content that sells.';
    }

    public static function description(): string
    {
        return Offering::whatsappEnabled()
            ? 'One platform to connect with customers, sell products, accept payments and automate your business.'
            : 'Schedule social posts, attach product offers, sell from your catalog, and collect M-Pesa — one social commerce platform for modern businesses.';
    }

    public static function positioning(): string
    {
        return 'The social commerce hub for modern businesses.';
    }

    public static function metaTitle(): string
    {
        return self::name().' — '.self::tagline();
    }

    public static function supportEmail(): string
    {
        return (string) config('settings.contact_email', 'hello@unganisha.com');
    }

    /**
     * @return array<int, array{name: string, blurb: string, href: string}>
     */
    public static function products(): array
    {
        $products = [
            ['name' => 'Unganisha Hub', 'blurb' => 'Your business command center.', 'href' => '#platform'],
            ['name' => 'Unganisha Social', 'blurb' => 'Compose, schedule, and publish to Facebook, Instagram, LinkedIn, and more.', 'href' => '#social'],
            ['name' => 'Unganisha Store', 'blurb' => 'Products, services, catalogs and storefronts.', 'href' => '#catalog'],
            ['name' => 'Unganisha Pay', 'blurb' => 'M-Pesa, cards and other payment methods.', 'href' => '#collections'],
            ['name' => 'Unganisha Flow', 'blurb' => 'Customer journeys and automation.', 'href' => '#automation'],
            ['name' => 'Unganisha AI', 'blurb' => 'AI captions and commerce assistants.', 'href' => '#features'],
            ['name' => 'Unganisha Orders', 'blurb' => 'Orders, invoices and fulfillment.', 'href' => '#collections'],
            ['name' => 'Unganisha Insights', 'blurb' => 'Which posts drove clicks, carts, and paid orders.', 'href' => '#features'],
            ['name' => 'Unganisha API', 'blurb' => 'Infrastructure for developers and integrations.', 'href' => '#api'],
        ];

        if (Offering::whatsappEnabled()) {
            array_splice($products, 2, 0, [
                ['name' => 'Unganisha Inbox', 'blurb' => 'Customer conversations across WhatsApp, Instagram, Messenger, WhatsApp Calls, and programmable phone.', 'href' => '#features'],
            ]);
            array_splice($products, 6, 0, [
                ['name' => 'Unganisha Campaigns', 'blurb' => 'WhatsApp, SMS and email marketing.', 'href' => '#campaigns'],
                ['name' => 'Unganisha Bookings', 'blurb' => 'Appointments and reservations.', 'href' => '#bookings'],
            ]);
        } else {
            $products[] = ['name' => 'Unganisha Bookings', 'blurb' => 'Appointments and reservations.', 'href' => '#bookings'];
        }

        return $products;
    }

    public static function markUrl(): string
    {
        return asset('landing/unganisha/mark.png');
    }

    public static function logoUrl(): string
    {
        return asset('landing/unganisha/logo.png');
    }

    public static function logoOnDarkUrl(): string
    {
        return asset('landing/unganisha/logo-on-dark.png');
    }

    public static function ogImageUrl(): string
    {
        return asset('landing/unganisha/og.png');
    }

    public static function faviconVersion(): string
    {
        $path = public_path('landing/unganisha/favicon.ico');

        return (string) (@filemtime($path) ?: '1');
    }
}
