@php
    $closures = $closures ?? [];
@endphp
<div class="col-md-12 mb-3">
    <label class="form-control-label">{{ __('Holidays & closures') }}</label>
    <small class="form-text text-muted d-block mb-2">
        {{ __('Dates when this department is closed. Services in this department will not offer slots on these days.') }}
    </small>
    <div id="department-closures" class="d-flex flex-column gap-2">
        @foreach ($closures as $index => $closure)
            <div class="row align-items-end department-closure-row">
                <div class="col-md-4">
                    <label class="form-control-label">{{ __('Label') }}</label>
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        name="closures[{{ $index }}][label]"
                        value="{{ $closure->label }}"
                        placeholder="{{ __('Public holiday') }}"
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-control-label">{{ __('Starts') }}</label>
                    <input
                        type="date"
                        class="form-control form-control-sm"
                        name="closures[{{ $index }}][starts_on]"
                        value="{{ $closure->starts_on?->format('Y-m-d') }}"
                        required
                    >
                </div>
                <div class="col-md-3">
                    <label class="form-control-label">{{ __('Ends') }}</label>
                    <input
                        type="date"
                        class="form-control form-control-sm"
                        name="closures[{{ $index }}][ends_on]"
                        value="{{ $closure->ends_on?->format('Y-m-d') }}"
                    >
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-closure-row">{{ __('Remove') }}</button>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-closure-row">{{ __('Add closure') }}</button>
</div>

<template id="closure-row-template">
    <div class="row align-items-end department-closure-row">
        <div class="col-md-4">
            <label class="form-control-label">{{ __('Label') }}</label>
            <input type="text" class="form-control form-control-sm" data-name="label" placeholder="{{ __('Public holiday') }}">
        </div>
        <div class="col-md-3">
            <label class="form-control-label">{{ __('Starts') }}</label>
            <input type="date" class="form-control form-control-sm" data-name="starts_on" required>
        </div>
        <div class="col-md-3">
            <label class="form-control-label">{{ __('Ends') }}</label>
            <input type="date" class="form-control form-control-sm" data-name="ends_on">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger remove-closure-row">{{ __('Remove') }}</button>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('department-closures');
    const template = document.getElementById('closure-row-template');
    const addButton = document.getElementById('add-closure-row');

    if (! container || ! template || ! addButton) {
        return;
    }

    let nextIndex = container.querySelectorAll('.department-closure-row').length;

    addButton.addEventListener('click', function () {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.department-closure-row');

        row.querySelectorAll('[data-name]').forEach(function (input) {
            const key = input.getAttribute('data-name');
            input.setAttribute('name', 'closures[' + nextIndex + '][' + key + ']');
            input.removeAttribute('data-name');
        });

        container.appendChild(row);
        nextIndex++;
    });

    container.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-closure-row')) {
            event.target.closest('.department-closure-row')?.remove();
        }
    });
});
</script>
