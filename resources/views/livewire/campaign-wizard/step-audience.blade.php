<div class="card shadow">
    <div class="card-body">
        @if ($broadcastType === 'group')
            @if ($contactId)
                <input type="hidden" wire:model="contactId">
                <p class="text-muted">{{ __('Sending to a single contact from chat.') }}</p>
            @else
                <div class="form-group">
                    <label>{{ __('Contact group') }}</label>
                    <select class="form-control" wire:model.live="groupId">
                        @foreach ($groups as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ __('Or saved segment') }}</label>
                    <select class="form-control" wire:model.live="segmentId">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($segments as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">
                        <a href="{{ route('campaigns.segments.index') }}">{{ __('Manage segments') }}</a>
                    </small>
                </div>
            @endif
        @elseif ($broadcastType === 'quick')
            <div class="form-group">
                <label>{{ __('Phone numbers') }} <span class="text-danger">*</span></label>
                <textarea class="form-control" rows="8" wire:model.live="quickPhones"
                          placeholder="{{ __('One per line or comma-separated with country code') }}"></textarea>
                @error('quickPhones') <small class="text-danger d-block">{{ $message }}</small> @enderror
            </div>
        @else
            <div class="form-group">
                <label>{{ __('Upload CSV or Excel') }} <span class="text-danger">*</span></label>
                <input type="file" class="form-control" wire:model="contactFile" accept=".csv,.xlsx,.xls,.txt">
                @error('contactFile') <small class="text-danger d-block">{{ $message }}</small> @enderror
                <div wire:loading wire:target="contactFile" class="text-muted small mt-1">{{ __('Reading file...') }}</div>
            </div>
            @if ($fileRowCount > 0)
                <p class="mb-2">
                    <span class="badge badge-success">{{ $fileRowCount }} {{ __('rows') }}</span>
                </p>
                <div class="form-group">
                    <label>
                        @if ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_EMAIL)
                            {{ __('Email column') }}
                        @else
                            {{ __('Phone column') }}
                        @endif
                    </label>
                    <select class="form-control" wire:model.live="recipientColumn">
                        @foreach ($fileHeaders as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
                        @endforeach
                    </select>
                    @error('recipientColumn') <small class="text-danger d-block">{{ $message }}</small> @enderror
                </div>
            @endif
        @endif
    </div>
</div>
