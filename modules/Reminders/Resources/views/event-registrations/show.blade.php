@extends('general.index', $setup)
@section('cardbody')
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Registration summary') }}</h4>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Status') }}</dt>
                    <dd class="col-sm-8">
                        <span class="badge {{ $registration->displayStatusBadgeClass() }}">{{ $registration->displayStatusLabel() }}</span>
                    </dd>

                    <dt class="col-sm-4">{{ __('Event') }}</dt>
                    <dd class="col-sm-8">{{ $registration->event?->title ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Starts') }}</dt>
                    <dd class="col-sm-8">{{ $registration->occurrence?->starts_at?->format('l, M j, Y g:i A') ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Ends') }}</dt>
                    <dd class="col-sm-8">{{ $registration->occurrence?->ends_at?->format('l, M j, Y g:i A') ?? '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Party size') }}</dt>
                    <dd class="col-sm-8">{{ $registration->party_size }}</dd>

                    <dt class="col-sm-4">{{ __('Reference') }}</dt>
                    <dd class="col-sm-8">{{ $registration->external_id ?: '—' }}</dd>

                    <dt class="col-sm-4">{{ __('Registered at') }}</dt>
                    <dd class="col-sm-8">{{ $registration->registered_at?->format('M j, Y g:i A') ?? '—' }}</dd>

                    @if ($registration->cancelled_at)
                        <dt class="col-sm-4">{{ __('Cancelled at') }}</dt>
                        <dd class="col-sm-8">{{ $registration->cancelled_at->format('M j, Y g:i A') }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Guest') }}</h4>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Name') }}</dt>
                    <dd class="col-sm-8">{{ $registration->contact?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Phone') }}</dt>
                    <dd class="col-sm-8">{{ $registration->contact?->phone ?? '—' }}</dd>
                </dl>

                <div class="mt-4">
                    @if ($registration->contact_id)
                        <a href="{{ route('reminders.event-registrations.open-chat', ['eventRegistration' => $registration->id]) }}" class="btn btn-sm btn-primary mr-2 mb-2">
                            <i class="ni ni-chat-round"></i> {{ __('Message guest') }}
                        </a>
                    @endif
                    @if ($registration->isActive())
                        <a href="{{ route('reminders.event-registrations.cancel', ['eventRegistration' => $registration->id]) }}" class="btn btn-sm btn-outline-danger mb-2">
                            {{ __('Cancel registration') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <h4 class="mb-0">{{ __('Client notification messages') }}</h4>
    </div>
    <div class="card-body p-0">
        @if ($reminderMessages->isEmpty())
            <p class="text-muted mb-0 p-4">{{ __('No scheduled WhatsApp messages linked to this registration yet.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table align-items-center table-flush mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('Template') }}</th>
                            <th>{{ __('Preview') }}</th>
                            <th>{{ __('Scheduled') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reminderMessages as $message)
                            <tr>
                                <td>{{ $message->campaign?->name ?? __('Message') }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($message->value, 120) }}</td>
                                <td>{{ $message->created_at?->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
