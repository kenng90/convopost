@extends('layouts.app', ['title' => __('Content calendar')])

@section('content')
@php($workspace = $workspace ?? auth()->user()?->currentCompany())
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Content calendar') }}</h1>
                    <p class="mb-0 text-white opacity-8">
                        @if ($workspace)
                            {{ __('Workspace: :name — posts stay isolated to this organization.', ['name' => $workspace->name]) }}
                        @else
                            {{ __('See scheduled and published posts by day.') }}
                        @endif
                    </p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.index') }}" class="btn btn-sm btn-neutral">{{ __('Posts list') }}</a>
                    <a href="{{ route('social.home') }}" class="btn btn-sm btn-neutral">{{ __('Back to Social') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-12">
            @include('partials.flash')
            @include('social::partials.onboarding-checklist')
            @if ($workspace)
                <div class="alert alert-primary shadow-sm mb-3">
                    <strong>{{ __('Active Social workspace') }}:</strong>
                    {{ $workspace->name }}
                    <span class="text-sm d-block d-md-inline mt-1 mt-md-0 ml-md-1">
                        {{ __('Switch organizations in the sidebar to open another client calendar.') }}
                    </span>
                </div>
            @endif
            @livewire('social.content-calendar')
        </div>
    </div>
</div>
@endsection
