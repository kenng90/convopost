@if (! empty($onboarding) && ! ($onboarding['complete'] ?? false) && ! empty($onboarding['steps']))
    <div class="card shadow mb-4 border-0">
        <div class="card-header border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-0">{{ __('Social commerce checklist') }}</h3>
                <p class="mb-0 text-sm text-muted">
                    {{ __('Connect → publish → first attributed order (:done/:total).', [
                        'done' => $onboarding['completed_count'],
                        'total' => $onboarding['total'],
                    ]) }}
                </p>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="list-group list-group-flush">
                @foreach ($onboarding['steps'] as $step)
                    <div class="list-group-item px-0 d-flex align-items-start justify-content-between flex-wrap gap-2" wire:key="onboarding-{{ $step['key'] }}">
                        <div class="d-flex align-items-start gap-3">
                            <span class="avatar avatar-sm rounded-circle {{ $step['done'] ? 'bg-success' : 'bg-secondary' }} text-white d-inline-flex align-items-center justify-content-center" style="min-width: 2rem;">
                                @if ($step['done'])
                                    ✓
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>
                            <div>
                                <div class="font-weight-bold {{ $step['done'] ? 'text-success' : '' }}">
                                    {{ $step['title'] }}
                                </div>
                                <div class="text-sm text-muted">{{ $step['detail'] }}</div>
                            </div>
                        </div>
                        @if (! $step['done'] && ! empty($step['route']))
                            <a href="{{ route($step['route']) }}" class="btn btn-sm btn-primary">
                                {{ $step['cta'] }}
                            </a>
                        @elseif ($step['done'])
                            <span class="badge badge-success align-self-center">{{ __('Done') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
