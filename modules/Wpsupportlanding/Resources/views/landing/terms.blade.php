<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title>Terms of Service — {{ \Modules\Wpsupportlanding\Support\UnganishaBrand::name() }}</title>
    <meta name="description" content="Terms of Service for Unganisha, the social commerce platform for WhatsApp.">
    @include('wpsupportlanding::landing.partials.marketing_styles')
</head>
<body class="bg-paper text-ink min-h-screen">
    @include('wpsupportlanding::landing.partials.marketing_nav', ['hasBlog' => $hasBlog ?? false])

    <section class="relative pt-28 pb-16 noise hero-glow min-h-screen">
        <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6">
            <div class="mb-10">
                <div class="badge inline-flex mb-4">Legal</div>
                <h1 class="font-display text-4xl sm:text-5xl font-800 mb-3 text-ink">Terms of Service</h1>
                <p class="text-sm text-gray-500">Last updated: {{ $lastUpdated }}</p>
            </div>

            <div class="blog-prose text-sm sm:text-base">
                {!! $content !!}
            </div>

            <div class="mt-12 pt-8 border-t border-indigo/10 flex flex-wrap gap-4">
                <a href="{{ url('/') }}" class="text-sm text-gray-600 hover:text-ink transition-colors">← Back to home</a>
                @if (Route::has('policy.show'))
                    <a href="{{ route('policy.show') }}" class="text-sm text-gray-600 hover:text-ink transition-colors">Privacy Policy</a>
                @endif
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-ink transition-colors">Sign in</a>
                @endif
            </div>
        </div>
    </section>

    <footer class="border-t border-white/10 py-8" style="background:#12263A;">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 text-center">
            <p class="text-xs text-white/50">© {{ date('Y') }} {{ \Modules\Wpsupportlanding\Support\UnganishaBrand::name() }}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
