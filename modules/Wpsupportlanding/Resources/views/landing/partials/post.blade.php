@php
    /** @var \Modules\Blog\Models\Blog $post */
@endphp
<article class="max-w-3xl mx-auto">
    <a href="{{ url('/blog') }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-wa-green transition-colors mb-8">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        {{ __('Back to Blog') }}
    </a>

    <header class="mb-8">
        <div class="badge inline-flex mb-4">{{ __('Article') }}</div>
        <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-800 text-white leading-tight mb-4">{{ $post->title }}</h1>
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
            <time datetime="{{ $post->created_at }}">{{ $post->created_at->format('F j, Y') }}</time>
            <span>•</span>
            <span>{{ $post->read_time }} {{ __('min read') }}</span>
        </div>
    </header>

    @if($post->featured_image)
        <div class="mb-10 rounded-2xl overflow-hidden border border-white/10">
            <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" class="w-full h-auto max-h-[420px] object-cover">
        </div>
    @endif

    @if($post->excerpt)
        <p class="text-lg text-gray-300 mb-8 leading-relaxed border-l-2 border-wa-green pl-5">{{ $post->excerpt }}</p>
    @endif

    <div class="blog-prose text-base">
        {!! $post->content !!}
    </div>

    @if($post->meta_keywords)
        <div class="mt-12 pt-8 border-t border-white/10">
            <p class="text-sm text-gray-500"><span class="text-gray-400 font-medium">{{ __('Keywords') }}:</span> {{ $post->meta_keywords }}</p>
        </div>
    @endif
</article>
