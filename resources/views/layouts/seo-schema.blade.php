@php
    $schemaSiteName = $schemaSiteName ?? config('settings.site_name', config('app.name', 'ConvoConnect'));
    $schemaUrl = rtrim((string) config('app.url'), '/') ?: url('/');
    $schemaDescription = $schemaDescription ?? 'Sell, Support & Grow Across WhatsApp, Instagram & Messenger.';
    $schemaLogo = $schemaLogo ?? url('/android-chrome-192x192.png');
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $schemaUrl.'/#organization',
                'name' => $schemaSiteName,
                'url' => $schemaUrl,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $schemaLogo,
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $schemaUrl.'/#website',
                'name' => $schemaSiteName,
                'url' => $schemaUrl,
                'description' => $schemaDescription,
                'publisher' => [
                    '@id' => $schemaUrl.'/#organization',
                ],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
