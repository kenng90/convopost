@extends('layouts.app', ['title' => __('Connected accounts')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Connected accounts') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Connect Facebook Pages, Instagram, LinkedIn, and TikTok to publish from Unganisha Social.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.home') }}" class="btn btn-sm btn-neutral">{{ __('Back to Social') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row mb-4">
        <div class="col-12">
            @include('partials.flash')
        </div>
        @foreach ($providers as $provider)
            <div class="col-md-4 mb-3">
                <div class="card shadow h-100">
                    <div class="card-body d-flex flex-column">
                        <h3 class="mb-2">{{ __($provider['label']) }}</h3>
                        <p class="text-muted small flex-grow-1">{{ __('Authorize publishing for this network.') }}</p>
                        @if (\Illuminate\Support\Facades\Route::has($provider['connect_route']))
                            <a href="{{ route($provider['connect_route']) }}" class="btn btn-sm btn-primary">
                                {{ __('Connect :network', ['network' => __($provider['label'])]) }}
                            </a>
                        @else
                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                {{ __('Coming soon') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Your accounts') }}</h3>
                </div>
                @if ($accounts->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Network') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Connected') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    @php
                                        $provider = \Modules\Social\Enums\SocialProvider::tryFromString($account->provider);
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge {{ $provider?->badgeClass() ?? 'badge-secondary' }}">
                                                {{ $provider?->label() ?? ucfirst($account->provider) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                @if ($account->avatar)
                                                    <img src="{{ $account->avatar }}" alt="" class="avatar avatar-sm rounded-circle mr-2" width="32" height="32">
                                                @endif
                                                <div>
                                                    <strong>{{ $account->name ?: ($account->username ?: $account->external_id) }}</strong>
                                                    @if ($account->username)
                                                        <div class="text-muted small">{{ '@'.$account->username }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $account->isActive() ? 'success' : 'secondary' }}">
                                                {{ __(ucfirst($account->status)) }}
                                            </span>
                                            @if ($account->hasExpiredToken())
                                                <span class="badge badge-warning">{{ __('Token expired') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $account->created_at?->format('M d, Y') }}</td>
                                        <td>
                                            @if (\Illuminate\Support\Facades\Route::has('social.accounts.disconnect'))
                                                <form method="POST" action="{{ route('social.accounts.disconnect', $account) }}" onsubmit="return confirm('{{ __('Disconnect this account?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Disconnect') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer py-4">
                        {{ $accounts->links() }}
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No social accounts connected yet. Use a connect button above to get started.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
