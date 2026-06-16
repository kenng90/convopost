@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Event') }}</th>
    <th>{{ __('Date') }}</th>
    <th>{{ __('Guest') }}</th>
    <th>{{ __('Party size') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->event?->title }}</td>
            <td>{{ $item->occurrence?->starts_at?->format('M j, Y H:i') }}</td>
            <td>{{ $item->contact?->name }}</td>
            <td>{{ $item->party_size }}</td>
            <td><span class="badge {{ $item->displayStatusBadgeClass() }}">{{ $item->displayStatusLabel() }}</span></td>
            <td>
                <a href="{{ route($setup['webroute_path'].'show', [$setup['parameter_name'] => $item->id]) }}" class="btn btn-info btn-sm">
                    <i class="ni ni-zoom-split-in"></i>
                </a>
            </td>
        </tr>
    @endforeach
@endsection
