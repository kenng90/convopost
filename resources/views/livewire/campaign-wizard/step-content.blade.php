<div class="card shadow">
    <div class="card-header"><h3 class="mb-0">{{ __('Message content') }}</h3></div>
    <div class="card-body">
        @if ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP)
            <div class="form-group">
                <label>{{ __('WhatsApp template') }}</label>
                <select class="form-control" wire:model.live="templateId">
                    <option value="">{{ __('Select template') }}</option>
                    @foreach ($templateOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('templateId') <small class="text-danger d-block">{{ $message }}</small> @enderror
            </div>

            @if ($variables)
                @include('livewire.campaign-wizard.variables-whatsapp')
            @endif
        @elseif ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_SMS)
            <div class="form-group">
                <label>{{ __('SMS template') }}</label>
                <select class="form-control" wire:model.live="channelTemplateKey">
                    @foreach ($templateOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('Message') }}</label>
                <textarea class="form-control" rows="6" wire:model.live="smsBody"
                          @if($channelTemplateKey !== 'custom') readonly @endif></textarea>
                <small class="text-muted">{{ __('Merge tags:') }} @{{name}}, @{{phone}}, @{{email}}</small>
                @error('smsBody') <small class="text-danger d-block">{{ $message }}</small> @enderror
            </div>
        @else
            <div class="form-group">
                <label>{{ __('Email template') }}</label>
                <select class="form-control" wire:model.live="channelTemplateKey">
                    <option value="">{{ __('Custom content') }}</option>
                    @foreach ($templateOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ __('Subject') }}</label>
                <input type="text" class="form-control" wire:model.live="emailSubject">
                @error('emailSubject') <small class="text-danger d-block">{{ $message }}</small> @enderror
            </div>
            <div class="form-group">
                <label>{{ __('Body') }}</label>
                <textarea class="form-control" rows="8" wire:model.live="emailBody"></textarea>
                <small class="text-muted">{{ __('Merge tags:') }} @{{name}}, @{{phone}}, @{{email}}</small>
                @error('emailBody') <small class="text-danger d-block">{{ $message }}</small> @enderror
            </div>
        @endif
    </div>
</div>
