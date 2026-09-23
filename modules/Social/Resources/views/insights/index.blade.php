@extends('layouts.app', ['title' => __('Social insights')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Social insights') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Content reach and commerce attributed to your posts in one place.') }}</p>
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

    <div class="row mb-2">
        <div class="col-12">
            <h2 class="h4 text-white mb-3">{{ __('Content') }}</h2>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Published posts') }}</div>
                    <div class="h2 mb-0">{{ number_format($content['published_posts']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Connected accounts') }}</div>
                    <div class="h2 mb-0">{{ number_format($content['connected_accounts']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Reach') }}</div>
                    <div class="h2 mb-0">{{ number_format($content['reach']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Engagement') }}</div>
                    <div class="h2 mb-0">{{ number_format($content['engagement']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Comments') }}</div>
                    <div class="h2 mb-0">{{ number_format($content['comments']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Clicks') }}</div>
                    <div class="h2 mb-0">{{ number_format($content['clicks']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-2">
        <div class="col-12">
            <h2 class="h4 text-white mb-3">{{ __('Commerce') }}</h2>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Offer clicks') }}</div>
                    <div class="h2 mb-0">{{ number_format($commerce['offer_clicks']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Paid orders') }}</div>
                    <div class="h2 mb-0">{{ number_format($commerce['paid_orders']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Attributed revenue') }}</div>
                    <div class="h2 mb-0">{{ number_format($commerce['revenue'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Conversion') }}</div>
                    <div class="h2 mb-0">{{ number_format($commerce['conversion_rate'], 2) }}%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl mb-3">
            <div class="card shadow h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">{{ __('Avg. order value') }}</div>
                    <div class="h2 mb-0">{{ number_format($commerce['average_order_value'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Posts that sold') }}</h3>
                    <p class="mb-0 text-sm text-muted">{{ __('Published posts with paid attributed orders.') }}</p>
                </div>
                @if ($postsThatSold->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Post') }}</th>
                                    <th>{{ __('Offer clicks') }}</th>
                                    <th>{{ __('Paid orders') }}</th>
                                    <th>{{ __('Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($postsThatSold as $row)
                                    <tr wire:key="sold-{{ $row['post']->id }}">
                                        <td style="max-width: 320px;">
                                            <a href="{{ route('social.posts.show', $row['post']) }}" class="text-dark d-block text-truncate">
                                                {{ \Illuminate\Support\Str::limit($row['post']->defaultVersion?->content ?? __('(No content)'), 80) }}
                                            </a>
                                        </td>
                                        <td>{{ number_format($row['offer_clicks']) }}</td>
                                        <td>{{ number_format($row['paid_orders']) }}</td>
                                        <td>{{ number_format($row['revenue'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No attributed paid orders yet. Attach offers to posts to track sales here.') }}</p>
                    </div>
                @endif
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
                                    <th>{{ __('Comments') }}</th>
                                    <th>{{ __('Clicks') }}</th>
                                    <th>{{ __('Paid orders') }}</th>
                                    <th>{{ __('Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posts as $row)
                                    <tr wire:key="perf-{{ $row['post']->id }}">
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
                                        <td>{{ number_format($row['comments']) }}</td>
                                        <td>{{ number_format($row['clicks']) }}</td>
                                        <td>{{ number_format($row['paid_orders']) }}</td>
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
