<div class="card shadow">
    <div class="card-body">
        <div class="form-group">
            <label class="d-block">{{ __('Delivery timing') }}</label>
            <div class="custom-control custom-radio">
                <input type="radio" id="send_now_yes" class="custom-control-input" wire:model.live="sendNow" value="1">
                <label class="custom-control-label" for="send_now_yes">{{ __('Send now') }}</label>
            </div>
            <div class="custom-control custom-radio">
                <input type="radio" id="send_now_no" class="custom-control-input" wire:model.live="sendNow" value="0">
                <label class="custom-control-label" for="send_now_no">{{ __('Schedule') }}</label>
            </div>
        </div>

        @if (! $sendNow)
            <div class="form-group">
                <label>{{ __('Schedule send time') }}</label>
                <input type="datetime-local" class="form-control" wire:model.live="sendTime"
                       min="{{ now()->format('Y-m-d\TH:i') }}">
            </div>
            <div class="form-group">
                <label>{{ __('Timezone mode') }}</label>
                <select class="form-control" wire:model.live="timezoneMode">
                    <option value="{{ \Modules\Wpbox\Models\Campaign::TIMEZONE_MODE_CONTACT }}">{{ __('Per contact local time') }}</option>
                    <option value="{{ \Modules\Wpbox\Models\Campaign::TIMEZONE_MODE_BUSINESS }}">{{ __('Business timezone') }}</option>
                </select>
            </div>
        @endif

        <div class="form-group">
            <label>{{ __('Recurring (optional)') }}</label>
            <select class="form-control" wire:model.live="recurrenceInterval">
                <option value="">{{ __('One-time only') }}</option>
                <option value="daily">{{ __('Daily') }}</option>
                <option value="weekly">{{ __('Weekly') }}</option>
                <option value="monthly">{{ __('Monthly') }}</option>
            </select>
        </div>

        @if ($recurrenceInterval !== '')
            <div class="form-group">
                <label>{{ __('Number of runs (0 = unlimited)') }}</label>
                <input type="number" min="0" class="form-control" wire:model.live="recurrenceCount">
            </div>
        @endif
    </div>
</div>
