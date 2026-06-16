@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Title') }}</th>
    <th>{{ __('Published') }}</th>
    <th>{{ __('Occurrences') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->title }}</td>
            <td>{{ $item->is_published ? __('Yes') : __('No') }}</td>
            <td>{{ $item->occurrences_count }}</td>
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
