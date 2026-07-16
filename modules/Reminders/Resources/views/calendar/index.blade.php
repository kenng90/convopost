@extends('general.index', $setup)

@section('cardbody')
<style>
    .google-calendar-shell { display:flex; flex-direction:column; gap:1rem; }
    .google-calendar-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; }
    .google-calendar-toolbar select { max-width: 360px; }
    .google-calendar-frame-wrap {
        width: 100%;
        min-height: 720px;
        border: 1px solid #e9ecef;
        border-radius: .5rem;
        overflow: hidden;
        background: #fff;
    }
    .google-calendar-frame-wrap iframe {
        display: block;
        width: 100%;
        height: 720px;
        border: 0;
    }
    @media (max-width: 768px) {
        .google-calendar-frame-wrap,
        .google-calendar-frame-wrap iframe { min-height: 560px; height: 560px; }
        .google-calendar-toolbar select { max-width: 100%; width: 100%; }
    }
</style>

<div class="google-calendar-shell">
    @if (! $connected)
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h4 class="mb-2">{{ __('Connect Google Calendar') }}</h4>
                <p class="text-muted mb-3">
                    {{ __('Calendar view uses Google Calendar embeds. Connect your Google account in booking settings, then choose a calendar here.') }}
                </p>
                <a href="{{ $connectUrl }}" class="btn btn-primary">{{ __('Connect Google Calendar') }}</a>
                <a href="{{ $settingsUrl }}" class="btn btn-outline-secondary">{{ __('Booking settings') }}</a>
            </div>
        </div>
    @elseif (empty($calendars))
        <div class="alert alert-warning mb-0">
            {{ __('No Google calendars were found for this account. Check your Google Calendar connection and try again.') }}
            <a href="{{ $settingsUrl }}" class="alert-link">{{ __('Open booking settings') }}</a>
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="google-calendar-toolbar">
                    <label class="mb-0 font-weight-bold" for="google-calendar-picker">{{ __('Calendar') }}</label>
                    <select id="google-calendar-picker" class="form-control" aria-label="{{ __('Choose calendar') }}">
                        @php $groups = collect($calendars)->groupBy('group'); @endphp
                        @foreach ($groups as $group => $items)
                            <optgroup label="{{ $group }}">
                                @foreach ($items as $calendar)
                                    <option
                                        value="{{ $calendar['id'] }}"
                                        data-embed-url="{{ $calendar['embed_url'] }}"
                                        @selected($calendar['id'] === $defaultCalendarId)
                                    >
                                        {{ $calendar['label'] }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <span class="text-muted small ml-auto">{{ __('Timezone') }}: {{ $timezone }}</span>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    {{ __('Google only shows calendars that are set to public (or shared for viewing). If an iframe is empty, open that calendar in Google → Settings → Access permissions → Make available to public.') }}
                </p>
            </div>
        </div>

        <div class="google-calendar-frame-wrap">
            <iframe
                id="google-calendar-embed"
                src="{{ $embedUrl }}"
                title="{{ __('Google Calendar') }}"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
        </div>
    @endif
</div>

@if ($connected && ! empty($calendars))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('google-calendar-picker');
    const frame = document.getElementById('google-calendar-embed');
    if (!picker || !frame) return;

    const storageKey = 'bookingGoogleCalendarEmbedId';
    const savedId = localStorage.getItem(storageKey);
    if (savedId) {
        const savedOption = Array.from(picker.options).find((option) => option.value === savedId);
        if (savedOption) {
            picker.value = savedId;
            frame.src = savedOption.dataset.embedUrl;
        }
    }

    picker.addEventListener('change', function () {
        const option = picker.options[picker.selectedIndex];
        if (!option) return;
        frame.src = option.dataset.embedUrl;
        localStorage.setItem(storageKey, option.value);
    });
});
</script>
@endif
@endsection
