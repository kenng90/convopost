<div>
    <div class="card shadow">
        <div class="card-header border-0">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="mb-0">{{ $title }}</h3>
                </div>
                <div class="col-auto">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="previousPeriod">←</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="goToday">{{ __('Today') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="nextPeriod">→</button>
                    </div>
                    <a href="{{ route('social.posts.create') }}" class="btn btn-sm btn-primary ml-2">{{ __('Compose') }}</a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 social-calendar-grid">
                    <thead class="thead-light">
                        <tr>
                            @foreach ($weekdays as $weekday)
                                <th class="text-center small text-uppercase" style="width: 14.28%;">{{ __($weekday) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($days)->chunk(7) as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td
                                        class="align-top p-2 {{ $day['inPeriod'] ? '' : 'bg-light' }} {{ $day['isToday'] ? 'table-primary' : '' }}"
                                        style="height: 120px; min-width: 120px;"
                                        wire:key="day-{{ $day['date']->toDateString() }}"
                                    >
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="font-weight-bold {{ $day['inPeriod'] ? '' : 'text-muted' }}">
                                                {{ $day['date']->day }}
                                            </span>
                                            @if ($day['posts']->isNotEmpty())
                                                <span class="badge badge-secondary">{{ $day['posts']->count() }}</span>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach ($day['posts']->take(4) as $post)
                                                @php
                                                    $badge = match ($post->status) {
                                                        'published' => 'success',
                                                        'scheduled' => 'info',
                                                        'failed' => 'danger',
                                                        default => 'secondary',
                                                    };
                                                    $snippet = \Illuminate\Support\Str::limit($post->defaultVersion?->content ?? __('(No content)'), 36);
                                                    $time = ($post->scheduled_at ?? $post->published_at)?->format('H:i');
                                                @endphp
                                                <div
                                                    class="small rounded px-1 py-1 bg-white border"
                                                    title="{{ $snippet }}"
                                                    wire:key="post-{{ $post->id }}"
                                                >
                                                    <span class="badge badge-{{ $badge }}">{{ $time }}</span>
                                                    <span class="d-block text-truncate">{{ $snippet }}</span>
                                                </div>
                                            @endforeach
                                            @if ($day['posts']->count() > 4)
                                                <div class="small text-muted">+{{ $day['posts']->count() - 4 }} {{ __('more') }}</div>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
