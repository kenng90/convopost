<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Blog') }} — {{ config('app.name') }}</title>
    <meta name="description" content="{{ __('Insights on WhatsApp CRM, automation, and growing revenue with ConvoConnect.') }}">
    @include('wpsupportlanding::landing.partials.marketing_styles')
</head>
<body class="bg-[#040f0c] text-white min-h-screen" x-data="{ mobileOpen: false }">
    @include('wpsupportlanding::landing.partials.marketing_nav')

    <section class="relative pt-28 pb-16 noise hero-glow grid-pattern overflow-hidden">
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 text-center">
            <div class="badge inline-flex mb-5">{{ __('Resources') }}</div>
            <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-800 mb-4">
                {{ __('ConvoConnect') }} <span class="grad-text">{{ __('Blog') }}</span>
            </h1>
            <p class="text-lg text-gray-400 max-w-2xl mx-auto">
                {{ __('Practical guides on WhatsApp sales, automation, and customer engagement.') }}
            </p>
        </div>
    </section>

    <section class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 pb-20">
        @if($posts->count() === 0)
            <div class="text-center py-20 rounded-2xl border border-white/10 bg-white/[0.02]">
                <p class="text-gray-400 text-lg">{{ __('No published posts yet. Check back soon.') }}</p>
                <a href="{{ url('/') }}" class="inline-block mt-6 text-sm font-semibold text-wa-green hover:underline">{{ __('Back to home') }}</a>
            </div>
        @else
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($posts as $post)
                    <article class="blog-card card-lift flex flex-col h-full">
                        <a href="{{ url('/blog/'.$post->slug) }}" class="block aspect-[16/10] overflow-hidden bg-white/5">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center" style="background:linear-gradient(135deg,rgba(37,211,102,0.15),rgba(7,94,84,0.3));">
                                    <span class="font-display text-2xl text-wa-green/60">CC</span>
                                </div>
                            @endif
                        </a>
                        <div class="p-6 flex flex-col flex-1">
                            <div class="flex items-center justify-between text-xs uppercase tracking-wider mb-3">
                                <span class="text-wa-green font-semibold">{{ $post->created_at->format('M j, Y') }}</span>
                                <span class="text-gray-500">{{ $post->read_time }} {{ __('min read') }}</span>
                            </div>
                            <h2 class="font-display text-xl font-700 text-white mb-3 leading-snug">
                                <a href="{{ url('/blog/'.$post->slug) }}" class="hover:text-wa-green transition-colors">{{ $post->title }}</a>
                            </h2>
                            @if($post->excerpt)
                                <p class="text-gray-400 text-sm leading-relaxed mb-4 flex-1">{{ \Illuminate\Support\Str::limit($post->excerpt, 140) }}</p>
                            @endif
                            <a href="{{ url('/blog/'.$post->slug) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-wa-green hover:gap-2 transition-all mt-auto">
                                {{ __('Read article') }}
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($posts->hasPages())
                <div class="flex justify-center mt-14 gap-2 flex-wrap">
                    @if($posts->onFirstPage())
                        <span class="px-4 py-2 rounded-lg text-sm text-gray-600 border border-white/5 cursor-not-allowed">{{ __('Previous') }}</span>
                    @else
                        <a href="{{ $posts->previousPageUrl() }}" class="px-4 py-2 rounded-lg text-sm text-gray-300 border border-white/10 hover:border-wa-green/30 hover:text-white transition-colors">{{ __('Previous') }}</a>
                    @endif

                    @foreach($posts->getUrlRange(1, $posts->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $posts->currentPage() === $page ? 'bg-wa-green text-black' : 'text-gray-400 border border-white/10 hover:text-white' }}">{{ $page }}</a>
                    @endforeach

                    @if($posts->hasMorePages())
                        <a href="{{ $posts->nextPageUrl() }}" class="px-4 py-2 rounded-lg text-sm text-gray-300 border border-white/10 hover:border-wa-green/30 hover:text-white transition-colors">{{ __('Next') }}</a>
                    @else
                        <span class="px-4 py-2 rounded-lg text-sm text-gray-600 border border-white/5 cursor-not-allowed">{{ __('Next') }}</span>
                    @endif
                </div>
            @endif
        @endif
    </section>

    <footer class="border-t border-white/[0.05] py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
            <p>© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
            <a href="{{ url('/') }}" class="text-wa-green hover:underline">{{ __('Back to homepage') }}</a>
        </div>
    </footer>
</body>
</html>
