<div class="card shadow mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge badge-primary">{{ $presenter->channelLabel() }}</span>
            @if ($item->isBroadcast())
                <span class="badge badge-secondary">{{ $presenter->broadcastTypeLabel() }}</span>
            @endif
            @if ($item->status)
                <span class="badge badge-info">{{ $presenter->statusLabel() }}</span>
            @endif
            @if ($item->ab_variant)
                <span class="badge badge-light text-dark">{{ __('A/B') }}: {{ strtoupper($item->ab_variant) }}</span>
            @endif
        </div>

        @if ($presenter->scheduleSummary())
            <p class="text-muted mb-0 small">{{ $presenter->scheduleSummary() }}</p>
        @endif
    </div>
</div>
