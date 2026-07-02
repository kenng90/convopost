@if (isset($analytics))
    <div class="card shadow mt-4">
        <div class="card-body">
            <h4 class="mb-3">{{ __('Send progress') }}</h4>
            <div class="progress mb-2" style="height: 22px;">
                <div class="progress-bar bg-success" style="width: {{ $analytics['progress_percent'] }}%">
                    {{ $analytics['progress_percent'] }}%
                </div>
            </div>
            <div class="row text-center">
                <div class="col">
                    <small class="text-muted d-block">{{ __('Pending') }}</small>
                    <strong>{{ $analytics['pending_count'] }}</strong>
                </div>
                <div class="col">
                    <small class="text-muted d-block">{{ __('Sent') }}</small>
                    <strong>{{ $analytics['sent_count'] }}</strong>
                </div>
                @if ($analytics['show_delivered_metric'] ?? false)
                    <div class="col">
                        <small class="text-muted d-block">{{ __('Delivered') }}</small>
                        <strong>{{ $analytics['delivered_count'] }}</strong>
                    </div>
                @endif
                <div class="col">
                    <small class="text-muted d-block">{{ __('Failed') }}</small>
                    <strong>{{ $analytics['failed_count'] }}</strong>
                </div>
                <div class="col">
                    <small class="text-muted d-block">{{ __('Failure rate') }}</small>
                    <strong>{{ $analytics['failure_rate'] }}%</strong>
                </div>
            </div>
            @if (! empty($analytics['failed_by_reason']))
                <hr>
                <h5>{{ __('Failure breakdown') }}</h5>
                <ul class="mb-0 pl-3">
                    @foreach ($analytics['failed_by_reason'] as $reason => $count)
                        <li>{{ $reason ?: __('Unknown') }} — {{ $count }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
