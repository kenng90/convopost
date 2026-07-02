@if ($variables)
    @foreach ($variables as $section => $itemBox)
        <h5 class="mt-3">{{ __(ucfirst($section)) }}</h5>
        @if ($section === 'header' || $section === 'body')
            @foreach ($itemBox as $item)
                <div class="form-group border-bottom pb-3">
                    <label>{{ __('Variable') }} {{ $item['id'] }}</label>
                    <input type="text" class="form-control"
                           wire:model.live="paramvalues.{{ $section }}.{{ $item['id'] }}"
                           placeholder="{{ $item['exampleValue'] ?? '' }}">
                    <select class="form-control mt-2"
                            wire:model.live="parammatch.{{ $section }}.{{ $item['id'] }}">
                        @foreach ($contactFields as $fieldId => $fieldLabel)
                            <option value="{{ $fieldId }}">{{ $fieldLabel }}</option>
                        @endforeach
                    </select>
                    @if ($broadcastType === 'file' && count($fileHeaders) > 0)
                        <select class="form-control mt-2"
                                wire:model.live="fileColumnMap.{{ $section }}.{{ $item['id'] }}">
                            <option value="">{{ __('Or map from file column') }}</option>
                            @foreach ($fileHeaders as $header)
                                <option value="{{ $header }}">{{ $header }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach
        @elseif ($section === 'document')
            <input type="file" class="form-control" wire:model="pdf" accept="application/pdf">
        @elseif ($section === 'image')
            <input type="file" class="form-control" wire:model="imageupload" accept=".jpg,.jpeg,.png">
        @elseif ($section === 'video')
            <input type="file" class="form-control" wire:model="imageupload" accept=".mp4">
        @elseif ($section === 'buttons')
            @foreach ($itemBox as $button)
                @foreach ($button as $keybtn => $item)
                    <div class="form-group">
                        <label>{{ $item['text'] ?? __('Button') }}</label>
                        <input type="text" class="form-control"
                               wire:model.live="paramvalues.buttons.{{ $keybtn }}.{{ $item['id'] }}">
                        <select class="form-control mt-2"
                                wire:model.live="parammatch.buttons.{{ $keybtn }}.{{ $item['id'] }}">
                            @foreach ($contactFields as $fieldId => $fieldLabel)
                                <option value="{{ $fieldId }}">{{ $fieldLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            @endforeach
        @endif
    @endforeach
@endif
