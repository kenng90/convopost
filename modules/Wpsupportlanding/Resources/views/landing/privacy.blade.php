<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title>Privacy Policy — {{ config('app.name', 'ConvoConnect') }}</title>
    <meta name="description" content="Privacy Policy for ConvoConnect, the social commerce platform for WhatsApp.">
    @include('wpsupportlanding::landing.partials.marketing_styles')
</head>
<body class="bg-[#040f0c] text-white min-h-screen">
    @include('wpsupportlanding::landing.partials.marketing_nav', ['hasBlog' => $hasBlog ?? false])

    <section class="relative pt-28 pb-16 noise hero-glow min-h-screen">
        <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6">
            <div class="mb-10">
                <div class="badge inline-flex mb-4">Legal</div>
                <h1 class="font-display text-4xl sm:text-5xl font-800 mb-3">Privacy Policy</h1>
                <p class="text-sm text-gray-500">Last updated: {{ $lastUpdated }}</p>
            </div>

            <div class="blog-prose text-sm sm:text-base">
                {!! $content !!}
            </div>

            <div class="mt-12 pt-8 border-t border-white/10 flex flex-wrap gap-4">
                <a href="{{ url('/') }}" class="text-sm text-gray-400 hover:text-white transition-colors">← Back to home</a>
                @if (Route::has('terms.show'))
                    <a href="{{ route('terms.show') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Terms of Service</a>
                @endif
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Sign in</a>
                @endif
            </div>
        </div>
    </section>

    <footer class="border-t border-white/[0.05] py-8" style="background:#020907;">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 text-center">
            <p class="text-xs text-gray-600">© {{ date('Y') }} {{ config('app.name', 'ConvoConnect') }}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
