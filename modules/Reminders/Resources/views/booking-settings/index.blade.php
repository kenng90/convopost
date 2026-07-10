@extends('general.index', $setup)

@section('cardbody')
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h3 class="mb-3">{{ __('Google Calendar') }}</h3>
        <p class="text-muted">{{ __('Connect your personal Google Calendar so availability reflects your busy times and new appointments appear on your calendar. This applies to one-to-one appointments only — events use fixed session dates and do not sync to Google Calendar.') }}</p>

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
                    <label for="google_calendar_id">{{ __('Default calendar') }}</label>
                    @if (! empty($calendars))
                        <select class="form-control" id="google_calendar_id" name="google_calendar_id" required>
                            @foreach ($calendars as $calendar)
                                <option value="{{ $calendar['id'] }}" @selected(old('google_calendar_id', $calendarId) === $calendar['id'])>
                                    {{ $calendar['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">{{ __('Used when a service does not specify its own calendar.') }}</small>
                    @else
                        <div class="alert alert-warning mb-0">{{ __('Could not load calendars from Google. Try reconnecting your account.') }}</div>
                    @endif
                </div>
                @if (! empty($calendars))
                    <button type="submit" class="btn btn-primary">{{ __('Save default calendar') }}</button>
                @endif
            </form>

            <a href="{{ route('reminders.google.disconnect') }}" class="btn btn-outline-danger">{{ __('Disconnect Google Calendar') }}</a>
        @else
            <a href="{{ route('reminders.google.connect') }}" class="btn btn-primary">{{ __('Connect Google Calendar') }}</a>
        @endif

        @if ($catalogUrl ?? null)
            <hr class="my-4">
            <h3 class="mb-2">{{ __('Public booking page') }}</h3>
            <p class="text-muted">{{ __('Share this link so customers can choose a service and book. No API token is needed in the URL.') }}</p>
            <code class="d-block p-2 bg-light rounded user-select-all">{{ $catalogUrl }}</code>
        @endif

        @if (($eventsEnabled ?? false) && ($eventsCatalogUrl ?? null))
            <hr class="my-4">
            <h3 class="mb-2">{{ __('Public events page') }}</h3>
            <p class="text-muted">{{ __('Share this link so guests can register for upcoming events.') }}</p>
            <code class="d-block p-2 bg-light rounded user-select-all">{{ $eventsCatalogUrl }}</code>
        @endif

        @if ($bookingPublicKey ?? null)
            <hr class="my-4">
            <h3 class="mb-2">{{ __('Booking API key') }}</h3>
            <p class="text-muted">{{ __('Use this scoped key for custom widgets and server-side integrations. Do not put your owner API token in public URLs.') }}</p>
            <code class="d-block p-2 bg-light rounded user-select-all mb-3">{{ $bookingPublicKey }}</code>
            <form method="POST" action="{{ route('reminders.booking-settings.regenerate-key') }}" onsubmit="return confirm('{{ __('Regenerating will invalidate the current key. Continue?') }}');">
                @csrf
                <button type="submit" class="btn btn-outline-warning btn-sm">{{ __('Regenerate booking key') }}</button>
            </form>
        @endif

        <hr class="my-4">
        <h3 class="mb-2">{{ __('Team inbox') }}</h3>
        <p class="text-muted">{{ __('By default, customers who book an appointment or register for an event do not appear in the chat inbox. Agents can open a conversation from the booking or registration detail page when needed.') }}</p>

        <form method="POST" action="{{ route('reminders.booking-settings.inbox') }}">
            @csrf
            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="booking_contacts_in_inbox" name="booking_contacts_in_inbox" value="1" @if($bookingContactsInInbox) checked @endif>
                <label class="custom-control-label" for="booking_contacts_in_inbox">{{ __('Show new appointment and event customers in inbox automatically') }}</label>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Save inbox settings') }}</button>
        </form>
    </div>
</div>
@endsection
