@extends('layouts.app', ['title' => __('Social posts')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Social posts') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Draft, schedule, and track publishing across your connected accounts.') }}</p>
                </div>
                <div class="col-auto">
                    @if (\Illuminate\Support\Facades\Route::has('social.posts.create'))
                        <a href="{{ route('social.posts.create') }}" class="btn btn-sm btn-primary">{{ __('Compose') }}</a>
                    @endif
                    <a href="{{ route('social.home') }}" class="btn btn-sm btn-neutral">{{ __('Back to Social') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row mb-3">
        <div class="col-12">
            @include('partials.flash')
        </div>
        <div class="col-12">
            <div class="btn-group flex-wrap" role="group">
                @foreach ($statusFilters as $key => $label)
                    <a
                        href="{{ route('social.posts.index', ['status' => $key === 'all' ? null : $key]) }}"
                        class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Posts') }}</h3>
                </div>
                @if ($posts->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Content') }}</th>
                                    <th>{{ __('Accounts') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Schedule') }}</th>
                                    <th>{{ __('Offer') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posts as $post)
                                    <tr>
                                        <td style="max-width: 320px;">
                                            <div class="text-truncate">
                                                {{ \Illuminate\Support\Str::limit($post->defaultVersion?->content ?? __('(No content)'), 120) }}
                                            </div>
                                        </td>
                                        <td>
                                            @forelse ($post->accounts as $account)
                                                <span class="badge badge-secondary mr-1">{{ $account->name ?: $account->provider }}</span>
                                            @empty
                                                <span class="text-muted">{{ __('None') }}</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @php
                                                $badge = match ($post->status) {
                                                    'published' => 'success',
                                                    'scheduled' => 'info',
                                                    'failed' => 'danger',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-{{ $badge }}">{{ __(ucfirst($post->status)) }}</span>
                                        </td>
                                        <td>
                                            @if ($post->scheduled_at)
                                                {{ $post->scheduled_at->format('M d, Y H:i') }}
                                            @elseif ($post->published_at)
                                                {{ $post->published_at->format('M d, Y H:i') }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($post->offerLink)
                                                <span class="badge badge-primary">{{ __(ucfirst($post->offerLink->offer_type)) }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer py-4">
                        {{ $posts->links() }}
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No posts in this filter yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
