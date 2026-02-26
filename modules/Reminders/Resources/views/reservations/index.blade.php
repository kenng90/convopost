@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Source') }}</th>
    <th>{{ __('Date Start') }}</th>
    <th>{{ __('Date End') }}</th>
    <th>{{ __('Reference') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>
                <a href="{{ route('contacts.edit', ['contact' => $item->contact->id]) }}" class="btn btn-primary btn-sm">
                    {{ $item->contact->name }}
                </a>
            </td>
            <td>{{ $item->source->name }}</td>
            <td>{{ $item->start_date }}</td>
            <td>{{ $item->end_date }}</td>
            <td>{{ $item->external_id }}</td>
            <td>
                <!-- EDIT -->
                <a href="{{ route('reminders.reservations.edit',['reservation'=>$item->id]) }}" class="btn btn-primary btn-sm">
                    <i class="ni ni-ruler-pencil"></i>
                </a>

                <!-- EDIT -->
                <a href="{{ route('reminders.reservations.delete',['reservation'=>$item->id]) }}" class="btn btn-danger btn-sm">
                    <i class="ni ni ni-fat-remove"></i>
                </a>
            </td>
        </tr> 
    @endforeach
@endsection