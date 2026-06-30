@extends('general.index', $setup)

@section('cardbody')
<div class="row">
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card card-stats h-100 mb-0">
            <div class="card-body">
                <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Upcoming appointments') }}</h5>
                <span class="h2 font-weight-bold mb-0">{{ $stats['upcoming_appointments'] }}</span>
            </div>
        </div>
    </div>
    @if ($eventsEnabled)
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="card card-stats h-100 mb-0">
                <div class="card-body">
                    <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Upcoming registrations') }}</h5>
                    <span class="h2 font-weight-bold mb-0">{{ $stats['upcoming_registrations'] }}</span>
                </div>
            </div>
        </div>
    @endif
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card card-stats h-100 mb-0">
            <div class="card-body">
                <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Services') }}</h5>
                <span class="h2 font-weight-bold mb-0">{{ $stats['services'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="card card-stats h-100 mb-0">
            <div class="card-body">
                <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Team members') }}</h5>
                <span class="h2 font-weight-bold mb-0">{{ $stats['team_members'] }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-lg-6 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h4 class="mb-2">{{ __('One-to-one appointments') }}</h4>
                <p class="text-muted mb-3">{{ __('Customers pick a service and an available time slot. Ideal for consultations, support calls, and scheduled visits.') }}</p>
                <ul class="text-muted small mb-4">
                    <li>{{ __('Set up services with duration and availability') }}</li>
                    <li>{{ __('Optionally assign team members and sync to Google Calendar') }}</li>
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('reminders.reservations.index') }}" class="btn btn-sm btn-primary">{{ __('Appointments') }}</a>
                    <a href="{{ route('reminders.sources.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Services') }}</a>
                </div>
            </div>
        </div>
    </div>

    @if ($eventsEnabled)
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h4 class="mb-2">{{ __('Events with limited seats') }}</h4>
                    <p class="text-muted mb-3">{{ __('Guests register for fixed sessions with capacity. No time-slot picker — you define dates and seat limits.') }}</p>
                    <ul class="text-muted small mb-4">
                        <li>{{ __('Create events and add session dates with capacity') }}</li>
                        <li>{{ __('Optionally assign a host from your booking team for WhatsApp alerts') }}</li>
                    </ul>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('reminders.events.index') }}" class="btn btn-sm btn-info">{{ __('Events') }}</a>
                        <a href="{{ route('reminders.event-registrations.index') }}" class="btn btn-sm btn-outline-info">{{ __('Registrations') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h4 class="mb-2">{{ __('Shared setup') }}</h4>
                <p class="text-muted mb-3">{{ __('Team and departments are shared by appointments and events. Configure them once under Bookings.') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('reminders.appointment-staff.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Team') }}</a>
                    <a href="{{ route('reminders.departments.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Departments') }}</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h4 class="mb-2">{{ __('Public pages') }}</h4>
                <p class="text-muted mb-3">{{ __('Share these links with customers. Manage URLs and API keys in booking settings.') }}</p>
                @if ($catalogUrl)
                    <p class="mb-1"><strong>{{ __('Appointments') }}</strong></p>
                    <code class="d-block p-2 bg-light rounded small mb-3 user-select-all">{{ $catalogUrl }}</code>
                @endif
                @if ($eventsCatalogUrl)
                    <p class="mb-1"><strong>{{ __('Events') }}</strong></p>
                    <code class="d-block p-2 bg-light rounded small mb-3 user-select-all">{{ $eventsCatalogUrl }}</code>
                @endif
                <a href="{{ $bookingSettingsUrl }}" class="btn btn-sm btn-outline-primary">{{ __('Booking settings') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
