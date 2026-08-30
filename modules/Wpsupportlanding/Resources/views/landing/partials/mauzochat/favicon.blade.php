@php
    $brand = \Modules\Wpsupportlanding\Support\MauzoChatBrand::class;
    $faviconVersion = $brand::faviconVersion();
@endphp
<link rel="apple-touch-icon" sizes="180x180" href="/landing/mauzochat/apple-touch-icon.png?v={{ $faviconVersion }}">
<link rel="icon" type="image/svg+xml" href="/landing/mauzochat/mark.svg">
<link rel="icon" type="image/png" sizes="192x192" href="/landing/mauzochat/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/landing/mauzochat/favicon-32x32.png?v={{ $faviconVersion }}">
<link rel="icon" type="image/png" sizes="16x16" href="/landing/mauzochat/favicon-16x16.png?v={{ $faviconVersion }}">
<link rel="shortcut icon" href="/landing/mauzochat/favicon.ico?v={{ $faviconVersion }}">
<link rel="manifest" href="/landing/mauzochat/site.webmanifest">
<meta name="theme-color" content="#0D1117">
