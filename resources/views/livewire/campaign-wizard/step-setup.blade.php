<div class="card shadow">
    <div class="card-body">
        <div class="form-group">
            <label>{{ __('Campaign name') }}</label>
            <input type="text" class="form-control" wire:model.live="name">
            @error('name') <small class="text-danger d-block">{{ $message }}</small> @enderror
        </div>

        <div class="form-group">
            <label>{{ __('Channel') }}</label>
            <select class="form-control" wire:model.live="channel">
                @foreach ($channels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <small class="text-muted">{{ __('Each channel uses its own templates. WhatsApp templates cannot be sent via SMS or email.') }}</small>
        </div>

        <div class="form-group">
            <label>{{ __('Broadcast type') }}</label>
            <div class="row">
                @foreach ($broadcastTypes as $type => $label)
                    @if ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_EMAIL && $type === 'quick')
                        @continue
                    @endif
                    <div class="col-md-4 mb-2">
                        <label class="btn btn-outline-primary btn-block {{ $broadcastType === $type ? 'active' : '' }}">
                            <input type="radio" wire:model.live="broadcastType" value="{{ $type }}" class="d-none">
                            {{ $label }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="form-group">
            <label>{{ __('A/B variant (optional)') }}</label>
            <select class="form-control" wire:model.live="abVariant">
                <option value="">{{ __('None') }}</option>
                <option value="a">A</option>
                <option value="b">B</option>
            </select>
        </div>
    </div>
</div>
