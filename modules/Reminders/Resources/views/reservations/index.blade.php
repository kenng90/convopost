@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Status') }}</th>
    <th>{{ __('Client') }}</th>
    <th>{{ __('Phone') }}</th>
    <th>{{ __('Service') }}</th>
    <th>{{ __('Team member') }}</th>
    <th>{{ __('Start') }}</th>
    <th>{{ __('Duration') }}</th>
    <th>{{ __('Reference') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>
                <span class="badge {{ $item->displayStatusBadgeClass() }}">{{ $item->displayStatusLabel() }}</span>
            </td>
            <td>
                <a href="{{ route('contacts.edit', ['contact' => $item->contact->id]) }}">
                    {{ $item->contact->name }}
                </a>
            </td>
            <td>{{ $item->contact->phone ?: '—' }}</td>
            <td>{{ $item->source->name }}</td>
            <td>{{ $item->appointmentStaffMember?->name ?? '—' }}</td>
            <td>{{ $item->start_date?->format('Y-m-d H:i') }}</td>
            <td>{{ $item->formattedDuration() ?? '—' }}</td>
            <td>{{ $item->external_id ?: '—' }}</td>
            <td>
                <a href="{{ route('reminders.reservations.show', ['reservation' => $item->id]) }}" class="btn btn-info btn-sm" title="{{ __('View details') }}">
                    <i class="ni ni-bullet-list-67"></i>
                </a>
                <a href="{{ route('reminders.reservations.edit', ['reservation' => $item->id]) }}" class="btn btn-primary btn-sm" title="{{ __('Edit') }}">
                    <i class="ni ni-ruler-pencil"></i>
                </a>
                <a href="{{ route('reminders.reservations.delete', ['reservation' => $item->id]) }}" class="btn btn-danger btn-sm" title="{{ __('Delete') }}">
                    <i class="ni ni-fat-remove"></i>
                </a>
            </td>
        </tr>
    @endforeach
@endsection
