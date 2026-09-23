@extends('layouts.app', ['title' => \Modules\Social\Support\SocialBrand::productName()])

@section('content')
@php
    $productName = \Modules\Social\Support\SocialBrand::productName();
    $platformName = \Modules\Social\Support\SocialBrand::platformName();
    $logoUrl = \Modules\Social\Support\SocialBrand::logoUrl();
@endphp
<div class="container-fluid py-4">
    <div class="row mt-3">
        <div class="col-12 col-lg-8">
            @if ($logoUrl)
                <div class="mb-3">
                    <img src="{{ $logoUrl }}" alt="{{ $platformName }}" style="max-height: 48px; max-width: 220px;" class="img-fluid">
                </div>
            @endif
            <h1 class="h3 mb-2">{{ $productName }}</h1>
            <p class="text-muted mb-4">
                {{ __('Publish and schedule content, attach product offers, and drive M-Pesa checkouts — your social commerce home on :brand.', ['brand' => $platformName]) }}
            </p>
            <div class="mb-4 d-flex flex-wrap gap-2">
                <a href="{{ route('social.accounts.index') }}" class="btn btn-primary">
                    {{ __('Connected accounts') }}
                </a>
                <a href="{{ route('social.media.index') }}" class="btn btn-outline-primary">
                    {{ __('Media library') }}
                </a>
                <a href="{{ route('social.posts.index') }}" class="btn btn-outline-primary">
                    {{ __('Posts') }}
                </a>
                <a href="{{ route('social.calendar') }}" class="btn btn-outline-primary">
                    {{ __('Calendar') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
