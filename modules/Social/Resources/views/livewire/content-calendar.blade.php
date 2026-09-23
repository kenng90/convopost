<div>
    <div class="card shadow">
        <div class="card-header border-0">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="mb-0">{{ $title }}</h3>
                    @if (! empty($workspaceName))
                        <div class="text-sm text-muted">{{ __('Workspace') }}: {{ $workspaceName }}</div>
                    @endif
                </div>
                <div class="col-auto">
                    <div class="btn-group mr-2" role="group">
                        <button
                            type="button"
                            class="btn btn-sm {{ $mode === 'month' ? 'btn-primary' : 'btn-outline-primary' }}"
                            wire:click="setMode('month')"
                        >{{ __('Month') }}</button>
                        <button
                            type="button"
                            class="btn btn-sm {{ $mode === 'week' ? 'btn-primary' : 'btn-outline-primary' }}"
                            wire:click="setMode('week')"
                        >{{ __('Week') }}</button>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="previousPeriod">←</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="goToday">{{ __('Today') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="nextPeriod">→</button>
                    </div>
                    <a href="{{ route('social.posts.create') }}" class="btn btn-sm btn-primary ml-2">{{ __('Compose') }}</a>
                </div>
            </div>
        </div>
        @if ($reschedulingPostId)
            <div class="card-body border-top bg-light">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label class="form-control-label" for="calendar-reschedule-at">{{ __('Reschedule post') }}</label>
                        <input
                            id="calendar-reschedule-at"
                            type="datetime-local"
                            class="form-control @error('rescheduleAt') is-invalid @enderror"
                            wire:model="rescheduleAt"
                        >
                        @error('rescheduleAt') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-primary" wire:click="saveReschedule" wire:loading.attr="disabled">
                            {{ __('Save new time') }}
                        </button>
                        <button type="button" class="btn btn-link" wire:click="cancelReschedule">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </div>
        @endif
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
                                        style="height: {{ $mode === 'week' ? '180px' : '120px' }}; min-width: 120px;"
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
                                            @foreach ($day['posts']->take($mode === 'week' ? 8 : 4) as $post)
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
                                                <button
                                                    type="button"
                                                    class="btn btn-link text-left small rounded px-1 py-1 bg-white border w-100"
                                                    title="{{ $snippet }}"
                                                    wire:key="post-{{ $post->id }}"
                                                    @if (in_array($post->status, ['draft', 'scheduled'], true))
                                                        wire:click="startReschedule({{ $post->id }})"
                                                    @else
                                                        disabled
                                                    @endif
                                                >
                                                    <span class="badge badge-{{ $badge }}">{{ $time }}</span>
                                                    <span class="d-block text-truncate text-dark">{{ $snippet }}</span>
                                                </button>
                                            @endforeach
                                            @if ($day['posts']->count() > ($mode === 'week' ? 8 : 4))
                                                <div class="small text-muted">+{{ $day['posts']->count() - ($mode === 'week' ? 8 : 4) }} {{ __('more') }}</div>
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
