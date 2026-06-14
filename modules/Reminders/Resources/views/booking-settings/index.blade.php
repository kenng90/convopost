@extends('general.index', $setup)

@section('cardbody')
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h3 class="mb-3">{{ __('Google Calendar') }}</h3>
        <p class="text-muted">{{ __('Connect your personal Google Calendar so availability reflects your busy times and new bookings appear on your calendar.') }}</p>

        <div class="alert alert-secondary small mb-4">
            <strong>{{ __('Google redirect URI') }}:</strong>
            <code class="d-block mt-1 user-select-all">{{ $redirectUri }}</code>
            <span class="text-muted d-block mt-2">{{ __('Add this exact URL under Authorized redirect URIs in Google Cloud Console → APIs & Services → Credentials → your OAuth client.') }}</span>
        </div>

        @if ($connected)
            <div class="alert alert-success">
                {{ __('Connected') }}
                @if ($connectedAt)
                    <span class="text-muted">({{ $connectedAt }})</span>
                @endif
            </div>

            <form method="POST" action="{{ route('reminders.booking-settings.calendar') }}" class="mb-4">
                @csrf
                <div class="form-group">
                    <label for="google_calendar_id">{{ __('Calendar ID') }}</label>
                    <input type="text" class="form-control" id="google_calendar_id" name="google_calendar_id" value="{{ old('google_calendar_id', $calendarId) }}" required>
                    <small class="form-text text-muted">{{ __('Use primary or a dedicated calendar email/ID.') }}</small>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save calendar ID') }}</button>
            </form>

            <a href="{{ route('reminders.google.disconnect') }}" class="btn btn-outline-danger">{{ __('Disconnect Google Calendar') }}</a>
        @else
            <a href="{{ route('reminders.google.connect') }}" class="btn btn-primary">{{ __('Connect Google Calendar') }}</a>
        @endif

        @if ($catalogUrl ?? null)
            <hr class="my-4">
            <h3 class="mb-2">{{ __('Public booking page') }}</h3>
            <p class="text-muted">{{ __('Share this link so customers can choose a service and book.') }}</p>
            <code class="d-block p-2 bg-light rounded user-select-all">{{ $catalogUrl }}?token=YOUR_API_TOKEN</code>
        @endif
    </div>
</div>
@endsection
