@php
    $chatDuka = \Modules\Wpsupportlanding\Support\ChatDukaBrand::class;
    $faviconVersion = $chatDuka::faviconVersion();
@endphp
<link rel="apple-touch-icon" sizes="180x180" href="/landing/chatduka/apple-touch-icon.png?v={{ $faviconVersion }}">
<link rel="icon" type="image/svg+xml" href="/landing/chatduka/mark.svg">
<link rel="icon" type="image/png" sizes="192x192" href="/landing/chatduka/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/landing/chatduka/favicon-32x32.png?v={{ $faviconVersion }}">
<link rel="icon" type="image/png" sizes="16x16" href="/landing/chatduka/favicon-16x16.png?v={{ $faviconVersion }}">
<link rel="shortcut icon" href="/landing/chatduka/favicon.ico?v={{ $faviconVersion }}">
<link rel="manifest" href="/landing/chatduka/site.webmanifest">
<meta name="theme-color" content="#1E2A5A">
