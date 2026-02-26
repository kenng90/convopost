@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
@foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>
                <!-- Kanban Edit -->
                <a href="{{ route('journies.kanban',['journey'=>$item->id]) }}" class="btn btn-success btn-sm" alt="{{__('Kanban')}}">
                    <i class="ni ni-folder-17"></i>
                </a>

                <!-- EDIT -->
                <a href="{{ route('journies.edit',['journey'=>$item->id]) }}" class="btn btn-primary btn-sm">
                    <i class="ni ni-ruler-pencil"></i>
                </a>

                

                <!-- DELETE -->
                <a href="{{ route('journies.delete',['journey'=>$item->id]) }}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this journey?')">
                    <i class="ni ni ni-fat-remove"></i>
                </a>
            </td>
        </tr> 
    @endforeach
@endsection
