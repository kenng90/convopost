<div class="card shadow">
    <div class="card-body">
        <h4>{{ __('Review & confirm') }}</h4>
        <ul class="list-unstyled mb-4">
            <li><strong>{{ __('Name') }}:</strong> {{ $name }}</li>
            <li><strong>{{ __('Channel') }}:</strong> {{ $channels[$channel] ?? $channel }}</li>
            <li><strong>{{ __('Broadcast type') }}:</strong> {{ $broadcastTypes[$broadcastType] ?? $broadcastType }}</li>
            <li><strong>{{ __('Subscribed recipients') }}:</strong> {{ $estimate['subscribed_count'] ?? 0 }}</li>
            <li><strong>{{ __('Excluded (opted out)') }}:</strong> {{ $estimate['excluded_count'] ?? 0 }}</li>
            <li><strong>{{ __('Credits per message') }}:</strong> {{ $estimate['credit_per_message'] ?? 0 }}</li>
            <li><strong>{{ __('Total credits') }}:</strong> {{ $estimate['total_credits'] ?? 0 }}</li>
        </ul>

        @error('credits') <div class="alert alert-danger">{{ $message }}</div> @enderror

        @if (isset($estimate['can_afford']) && ! $estimate['can_afford'] && config('settings.enable_credits', false))
            <div class="alert alert-warning">{{ __('Insufficient credits to launch this campaign.') }}</div>
        @endif
    </div>
</div>
