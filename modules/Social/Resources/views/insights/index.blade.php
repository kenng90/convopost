@extends('layouts.app', ['title' => __('Social insights')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Social insights') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Reach, engagement, and commerce attributed to your posts.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.index') }}" class="btn btn-sm btn-neutral">{{ __('Posts') }}</a>
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
    </div>

    <div class="row mb-4">
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Impressions') }}</div>
                    <div class="h2 mb-0">{{ number_format($totals['impressions']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Reach') }}</div>
                    <div class="h2 mb-0">{{ number_format($totals['reach']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Engagement') }}</div>
                    <div class="h2 mb-0">{{ number_format($totals['engagement']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Clicks') }}</div>
                    <div class="h2 mb-0">{{ number_format($totals['clicks']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Orders') }}</div>
                    <div class="h2 mb-0">{{ number_format($totals['orders']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Attributed revenue') }}</div>
                    <div class="h2 mb-0">{{ number_format($totals['revenue'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Post performance') }}</h3>
                </div>
                @if ($posts->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Post') }}</th>
                                    <th>{{ __('Reach') }}</th>
                                    <th>{{ __('Engagement') }}</th>
                                    <th>{{ __('Clicks') }}</th>
                                    <th>{{ __('Orders') }}</th>
                                    <th>{{ __('Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posts as $row)
                                    <tr>
                                        <td style="max-width: 280px;">
                                            <a href="{{ route('social.posts.show', $row['post']) }}" class="text-dark d-block text-truncate">
                                                {{ \Illuminate\Support\Str::limit($row['post']->defaultVersion?->content ?? __('(No content)'), 80) }}
                                            </a>
                                            <div class="small text-muted">
                                                {{ $row['post']->published_at?->format('M d, Y') ?? __(ucfirst($row['post']->status)) }}
                                                @foreach ($row['providers'] as $provider)
                                                    · {{ ucfirst($provider) }}
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>{{ number_format($row['reach']) }}</td>
                                        <td>{{ number_format($row['engagement']) }}</td>
                                        <td>{{ number_format($row['clicks']) }}</td>
                                        <td>{{ number_format($row['orders']) }}</td>
                                        <td>{{ number_format($row['revenue'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No posts yet. Publish content to see insights here.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
