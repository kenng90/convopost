@extends('layouts.app', ['title' => __('Compose social post')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Compose') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Write once, attach media, and publish to your connected accounts.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.index') }}" class="btn btn-sm btn-neutral">{{ __('Back to posts') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-12 col-lg-10">
            @include('partials.flash')
            @livewire('social.post-composer')
        </div>
    </div>
</div>
@endsection
