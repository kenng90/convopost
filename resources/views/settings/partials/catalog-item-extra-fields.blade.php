@props(['presentation', 'prefix' => 'new'])

@php
    $statusField = $presentation['status_field'] ?? 'listing_status';
    $statusOptions = $presentation['status_options'] ?? [];
@endphp

@foreach($presentation['item_fields'] ?? [] as $field)
    @php
        $key = $field['key'] ?? '';
        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
        $type = $field['type'] ?? 'text';
        $fieldId = $prefix . 'Item' . str_replace('_', '', ucwords($key, '_'));
    @endphp
    @if($key !== '')
        <div class="col-md-4 col-lg-3">
            <div class="form-group">
                <label for="{{ $fieldId }}">{{ __($label) }}</label>
                @if($type === 'select')
                    <select id="{{ $fieldId }}" class="form-control catalog-vertical-field" data-field-key="{{ $key }}">
                        <option value="">{{ __('Select...') }}</option>
                        @foreach($field['options'] ?? [] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif($type === 'number')
                    <input type="number" id="{{ $fieldId }}" class="form-control catalog-vertical-field" data-field-key="{{ $key }}" min="0" step="any">
                @else
                    <input type="text" id="{{ $fieldId }}" class="form-control catalog-vertical-field" data-field-key="{{ $key }}">
                @endif
            </div>
        </div>
    @endif
@endforeach
