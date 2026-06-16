@if ($message->status == 0)
    <span class="badge badge-warning">{{ __('Pending') }}</span>
@elseif ($message->status == 1)
    <span class="badge badge-warning">{{ __('Queued') }}</span>
@elseif ($message->status == 2)
    <span class="badge badge-info">{{ __('Sent') }}</span>
@elseif ($message->status == 3)
    <span class="badge badge-info">{{ __('Delivered') }}</span>
@elseif ($message->status == 4)
    <span class="badge badge-success">{{ __('Read') }}</span>
@elseif ($message->status == 5)
    <span class="badge badge-danger">{{ __('Failed') }}</span>
    @if ($message->error)
        <small class="d-block text-muted">{{ $message->error }}</small>
    @endif
@else
    <span class="badge badge-secondary">{{ __('Unknown') }}</span>
@endif
