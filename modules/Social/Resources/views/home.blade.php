@extends('layouts.app', ['title' => __('Unganisha Social')])

@section('content')
<div class="container-fluid py-4">
    <div class="row mt-3">
        <div class="col-12 col-lg-8">
            <h1 class="h3 mb-2">{{ __('Unganisha Social') }}</h1>
            <p class="text-muted mb-4">
                {{ __('Publish and schedule content, attach product offers, and drive M-Pesa checkouts — your social commerce home.') }}
            </p>
            <div class="card shadow">
                <div class="card-body">
                    <p class="mb-2 font-weight-bold">{{ __('Coming next') }}</p>
                    <ul class="mb-0 pl-3">
                        <li>{{ __('Connect Facebook, Instagram, and LinkedIn accounts') }}</li>
                        <li>{{ __('Compose, schedule, and calendar') }}</li>
                        <li>{{ __('Attach catalog offers with tracked links') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
