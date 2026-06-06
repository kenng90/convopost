@php
    /** @var \Modules\Blog\Models\Blog $post */
    $metaTitle = $post->meta_title ?: $post->title;
    $metaDescription = $post->meta_description ?: ($post->excerpt ?: strip_tags(\Illuminate\Support\Str::limit($post->content, 160)));
@endphp
<title>{{ $metaTitle }} — {{ config('app.name') }}</title>
<meta name="title" content="{{ $metaTitle }}">
<meta name="description" content="{{ $metaDescription }}">
@if($post->meta_keywords)
    <meta name="keywords" content="{{ $post->meta_keywords }}">
@endif
<meta property="og:type" content="article">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
@if($post->featured_image)
    <meta property="og:image" content="{{ $post->featured_image }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
@if($post->featured_image)
    <meta name="twitter:image" content="{{ $post->featured_image }}">
@endif
<meta property="article:published_time" content="{{ $post->created_at?->toIso8601String() }}">
@if($post->updated_at)
    <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
@endif
<link rel="canonical" href="{{ url()->current() }}">
