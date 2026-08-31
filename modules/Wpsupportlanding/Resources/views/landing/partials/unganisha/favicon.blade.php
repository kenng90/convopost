@php
    $brand = \Modules\Wpsupportlanding\Support\UnganishaBrand::class;
    $faviconVersion = $brand::faviconVersion();
@endphp
<link rel="apple-touch-icon" sizes="180x180" href="/landing/unganisha/apple-touch-icon.png?v={{ $faviconVersion }}">
<link rel="icon" type="image/svg+xml" href="/landing/unganisha/mark.svg">
<link rel="icon" type="image/png" sizes="192x192" href="/landing/unganisha/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/landing/unganisha/favicon-32x32.png?v={{ $faviconVersion }}">
<link rel="icon" type="image/png" sizes="16x16" href="/landing/unganisha/favicon-16x16.png?v={{ $faviconVersion }}">
<link rel="shortcut icon" href="/landing/unganisha/favicon.ico?v={{ $faviconVersion }}">
<link rel="manifest" href="/landing/unganisha/site.webmanifest">
<meta name="theme-color" content="#12263A">
