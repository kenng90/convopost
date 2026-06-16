@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Description') }}</th>
    <th>{{ __('Active') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ $item->description }}</td>
            <td>{{ $item->is_active ? __('Yes') : __('No') }}</td>
            <td>
                <a href="{{ route($setup['webroute_path'].'edit', [$setup['parameter_name'] => $item->id]) }}" class="btn btn-primary btn-sm">
                    <i class="ni ni-ruler-pencil"></i>
                </a>
                <a href="{{ route($setup['webroute_path'].'delete', [$setup['parameter_name'] => $item->id]) }}" class="btn btn-danger btn-sm">
                    <i class="ni ni-fat-remove"></i>
                </a>
            </td>
        </tr>
    @endforeach
@endsection
