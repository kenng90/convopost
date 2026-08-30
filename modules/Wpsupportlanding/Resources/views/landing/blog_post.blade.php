<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('wpsupportlanding::landing.partials.post_meta')
    @include('wpsupportlanding::landing.partials.marketing_styles')
    @if(config('blog.disqus_shortname'))
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @endif
</head>
<body class="bg-paper text-ink min-h-screen">
    @include('wpsupportlanding::landing.partials.marketing_nav')

    <section class="relative pt-28 pb-12 noise hero-glow">
        <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6">
            @include('wpsupportlanding::landing.partials.post')
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 pb-8">
        <div class="flex justify-center gap-4">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener" class="w-10 h-10 rounded-lg border border-indigo/10 flex items-center justify-center text-gray-500 hover:text-ink hover:border-saffron transition-all" aria-label="Share on Facebook">
                <i class="fab fa-facebook"></i>
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" class="w-10 h-10 rounded-lg border border-indigo/10 flex items-center justify-center text-gray-500 hover:text-ink hover:border-saffron transition-all" aria-label="Share on X">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(url()->current()) }}" target="_blank" rel="noopener" class="w-10 h-10 rounded-lg border border-indigo/10 flex items-center justify-center text-gray-500 hover:text-ink hover:border-saffron transition-all" aria-label="Share on LinkedIn">
                <i class="fab fa-linkedin"></i>
            </a>
        </div>
    </section>

    @if(config('blog.disqus_shortname'))
        <div id="disqus_thread" class="max-w-4xl mx-auto px-4 sm:px-6 pb-16"></div>
        <script>
            var disqus_config = function () {
                this.page.url = @json(url()->current());
                this.page.identifier = @json(Request::path());
            };
            (function() {
                var d = document, s = d.createElement('script');
                s.src = 'https://{{ config('blog.disqus_shortname') }}.disqus.com/embed.js';
                s.setAttribute('data-timestamp', +new Date());
                (d.head || d.body).appendChild(s);
            })();
        </script>
    @endif

    <footer class="border-t border-white/10 py-10" style="background:#0D1117;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-white/60">
            <p>© {{ date('Y') }} {{ \Modules\Wpsupportlanding\Support\MauzoChatBrand::name() }}. {{ __('All rights reserved.') }}</p>
            <a href="{{ url('/blog') }}" class="hover:text-white" style="color:#28B463;">{{ __('More articles') }}</a>
        </div>
    </footer>
</body>
</html>
