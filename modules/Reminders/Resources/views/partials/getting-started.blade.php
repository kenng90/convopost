@php
    $type = $gettingStartedType ?? 'default';
@endphp

<div class="alert alert-light border mb-0">
    <h5 class="alert-heading mb-2">{{ __('Getting started') }}</h5>

    @if ($type === 'team')
        <p class="mb-2 text-muted">{{ __('Your booking team is shared by appointments and events. Team members can be assigned to services or chosen as event hosts.') }}</p>
        <ol class="mb-0 pl-3 text-muted small">
            <li>{{ __('Add name, email, and optional WhatsApp phone for notifications') }}</li>
            <li>{{ __('Link a platform user if you need Google Calendar sync for appointments') }}</li>
            <li>{{ __('Assign team members on service forms, or pick a host when editing an event') }}</li>
        </ol>
    @elseif ($type === 'departments')
        <p class="mb-2 text-muted">{{ __('Departments organize appointment services and working hours. They are optional for events (label only).') }}</p>
        <ol class="mb-0 pl-3 text-muted small">
            <li>{{ __('Create a department with timezone and working hours') }}</li>
            <li>{{ __('Assign services and team members to departments') }}</li>
            <li>{{ __('Working hours and closures apply to appointment availability, not event sessions') }}</li>
        </ol>
    @elseif ($type === 'services')
        <p class="mb-2 text-muted">{{ __('Services are bookable appointment types customers can choose on your public booking page.') }}</p>
        <ol class="mb-0 pl-3 text-muted small">
            <li>{{ __('Create a service with duration and availability rules') }}</li>
            <li>{{ __('Assign team members and optional client WhatsApp notifications') }}</li>
            <li>{{ __('Share your public booking link from booking settings') }}</li>
        </ol>
    @elseif ($type === 'events')
        <p class="mb-2 text-muted">{{ __('Events are fixed sessions with seat capacity. Guests register — they do not pick a time slot.') }}</p>
        <ol class="mb-0 pl-3 text-muted small">
            <li>{{ __('Create an event and add session dates with capacity') }}</li>
            <li>{{ __('Publish the event and share your public events page') }}</li>
            <li>{{ __('Optionally add a host from Bookings → Team for WhatsApp alerts') }}</li>
        </ol>
    @elseif ($type === 'appointments')
        <p class="mb-2 text-muted">{{ __('Appointments appear here once customers book via your public page, WhatsApp flows, API, or manual entry.') }}</p>
        <ol class="mb-0 pl-3 text-muted small">
            <li>{{ __('Create at least one service under Bookings → Services') }}</li>
            <li>{{ __('Share your public booking link from booking settings') }}</li>
            <li>{{ __('Or add an appointment manually with the button above') }}</li>
        </ol>
    @else
        <p class="mb-0 text-muted">{{ __('Use the actions above to get started.') }}</p>
    @endif
</div>
