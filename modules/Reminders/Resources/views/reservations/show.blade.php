@extends('general.index', $setup)
@section('cardbody')
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Booking summary') }}</h4>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Status') }}</dt>
                    <dd class="col-sm-8">
                        <span class="badge {{ $reservation->displayStatusBadgeClass() }}">{{ $reservation->displayStatusLabel() }}</span>
                    </dd>

                    <dt class="col-sm-4">{{ __('Service') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->source?->name ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Location') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->source?->location ?: '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Department') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->source?->department?->name ?? __('All departments') }}</dd>

                    <dt class="col-sm-4">{{ __('Start') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->start_date?->format('l, M j, Y g:i A') ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('End') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->end_date?->format('l, M j, Y g:i A') ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Duration') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->formattedDuration() ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Reference') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->external_id ?: '—' }}</dd>

                    @if ($reservation->flow_id)
                        <dt class="col-sm-4">{{ __('Source flow') }}</dt>
                        <dd class="col-sm-8">
                            <a href="{{ route('flowmaker.edit', ['flow' => $reservation->flow_id]) }}">
                                {{ __('Flow') }} #{{ $reservation->flow_id }}
                            </a>
                            @if ($reservation->flow_node_id)
                                <span class="text-muted small"> · {{ __('Node') }} {{ $reservation->flow_node_id }}</span>
                            @endif
                        </dd>
                    @endif

                    <dt class="col-sm-4">{{ __('Booked on') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->created_at?->format('M j, Y g:i A') ?? '—' }}</dd>

                    @if ($reservation->cancelled_at)
                        <dt class="col-sm-4">{{ __('Cancelled at') }}</dt>
                        <dd class="col-sm-8">{{ $reservation->cancelled_at->format('M j, Y g:i A') }}</dd>
                    @endif

                    <dt class="col-sm-4">{{ __('Google Calendar') }}</dt>
                    <dd class="col-sm-8">
                        @if ($reservation->google_event_id)
                            <span class="badge badge-success">{{ __('Linked') }}</span>
                        @else
                            <span class="badge badge-secondary">{{ __('Not linked') }}</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Client & team') }}</h4>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Client') }}</dt>
                    <dd class="col-sm-8">
                        @if ($reservation->contact)
                            <a href="{{ route('contacts.edit', ['contact' => $reservation->contact_id]) }}">{{ $reservation->contact->name }}</a>
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="col-sm-4">{{ __('Phone') }}</dt>
                    <dd class="col-sm-8">{{ $reservation->contact?->phone ?: '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Team member') }}</dt>
                    <dd class="col-sm-8">
                        {{ $reservation->appointmentStaffMember?->name ?? $reservation->staff?->name ?? '—' }}
                        @if ($reservation->appointmentStaffMember?->departmentNamesLabel())
                            <small class="text-muted d-block">{{ $reservation->appointmentStaffMember->departmentNamesLabel() }}</small>
                        @endif
                    </dd>
                </dl>

                <div class="mt-4">
                    @if ($reservation->contact_id)
                        <a href="{{ route('reminders.reservations.open-chat', ['reservation' => $reservation->id]) }}" class="btn btn-sm btn-primary mr-2 mb-2">
                            <i class="ni ni-chat-round"></i> {{ __('Message customer') }}
                        </a>
                        <!-- <a href="{{ route('contacts.edit', ['contact' => $reservation->contact_id]) }}" class="btn btn-sm btn-outline-primary mr-2 mb-2">
                            {{ __('Contact profile') }}
                        </a> -->
                    @endif
                    @if ($reservation->source_id)
                        <a href="{{ route('reminders.sources.edit', ['source' => $reservation->source_id]) }}" class="btn btn-sm btn-outline-default mb-2">
                            {{ __('Service settings') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if ($reservation->notes)
    <div class="card shadow mb-4">
        <div class="card-header">
            <h4 class="mb-0">{{ __('Notes') }}</h4>
        </div>
        <div class="card-body">
            <p class="mb-0">{{ $reservation->notes }}</p>
        </div>
    </div>
@endif

<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">{{ __('Client notification messages') }}</h4>
        <a href="{{ route('reminders.reminders.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Client notifications') }}</a>
    </div>
    <div class="card-body p-0">
        @if ($reminderMessages->isEmpty())
            <p class="text-muted mb-0 p-4">{{ __('No scheduled WhatsApp messages linked to this reservation yet.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table align-items-center table-flush mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('Template') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Preview') }}</th>
                            <th>{{ __('Scheduled') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reminderMessages as $message)
                            <tr>
                                <td>{{ $message->campaign?->name ?? __('Notification message') }}</td>
                                <td>
                                    @if ($message->scchuduled_at && \Carbon\Carbon::parse($message->scchuduled_at)->greaterThan($message->created_at?->addMinute()))
                                        {{ __('Reminder') }}
                                    @else
                                        {{ __('Confirmation') }}
                                    @endif
                                </td>
                                <td class="text-wrap" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($message->value, 120) }}</td>
                                <td>{{ $message->scchuduled_at ? \Carbon\Carbon::parse($message->scchuduled_at)->format('M j, Y g:i A') : ($message->created_at?->format('M j, Y g:i A') ?? '—') }}</td>
                                <td>
                                    @include('reminders::reservations.partials.message-status', ['message' => $message])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
