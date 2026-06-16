@php
    $hours = \Modules\Reminders\Support\WorkingHours::normalize($value ?? null);
    $fieldId = $id ?? 'working_hours';
@endphp
<div class="{{ $class ?? 'col-md-12' }} mb-3">
    <label class="form-control-label">{{ $name ?? __('Working hours') }}</label>
    @if (! empty($additionalInfo))
        <small class="form-text text-muted d-block mb-2">{{ $additionalInfo }}</small>
    @endif
    <div class="table-responsive">
        <table class="table table-sm align-items-center">
            <thead>
                <tr>
                    <th>{{ __('Day') }}</th>
                    <th>{{ __('Open') }}</th>
                    <th>{{ __('Start') }}</th>
                    <th>{{ __('End') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hours as $day => $config)
                    <tr>
                        <td class="text-capitalize">{{ __($day) }}</td>
                        <td>
                            <input
                                type="checkbox"
                                name="{{ $fieldId }}[{{ $day }}][enabled]"
                                value="1"
                                @checked($config['enabled'])
                            >
                        </td>
                        <td>
                            <input
                                type="time"
                                class="form-control form-control-sm"
                                name="{{ $fieldId }}[{{ $day }}][start]"
                                value="{{ $config['start'] }}"
                            >
                        </td>
                        <td>
                            <input
                                type="time"
                                class="form-control form-control-sm"
                                name="{{ $fieldId }}[{{ $day }}][end]"
                                value="{{ $config['end'] }}"
                            >
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
