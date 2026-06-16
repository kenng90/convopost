@extends('general.index', $setup)
@section('customheading')
<div class="container-fluid mt-2">
    <div class="row">
        @foreach($templates as $key => $template)
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="mb-2">{{ $template['name'] }}</h5>
                        <p class="text-muted small flex-grow-1">{{ $template['description'] }}</p>
                        <a href="{{ route('journies.create-from-template', $key) }}" class="btn btn-sm btn-outline-primary mt-2">
                            {{ __('Use template') }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Description') }}</th>
    <th>{{ __('Stages') }}</th>
    <th>{{ __('Contacts') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
@foreach ($setup['items'] as $item)
    <tr>
        <td>
            <strong>{{ $item->name }}</strong>
        </td>
        <td>{{ \Illuminate\Support\Str::limit($item->description, 80) }}</td>
        <td><span class="badge badge-primary">{{ $item->stages_count }}</span></td>
        <td><span class="badge badge-info">{{ $item->contacts_count }}</span></td>
        <td>
            <a href="{{ route('journies.kanban',['journey'=>$item->id]) }}" class="btn btn-success btn-sm" title="{{ __('Kanban') }}">
                <i class="ni ni-folder-17"></i>
            </a>
            <a href="{{ route('journies.analytics', ['journey_id' => $item->id]) }}" class="btn btn-info btn-sm" title="{{ __('Analytics') }}">
                <i class="ni ni-chart-bar-32"></i>
            </a>
            <a href="{{ route('journies.group-rules', $item) }}" class="btn btn-warning btn-sm" title="{{ __('Group rules') }}">
                <i class="ni ni-badge"></i>
            </a>
            <a href="{{ route('journies.edit',['journey'=>$item->id]) }}" class="btn btn-primary btn-sm" title="{{ __('Edit') }}">
                <i class="ni ni-ruler-pencil"></i>
            </a>
            <form action="{{ route('journies.delete', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this journey?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm" title="{{ __('Delete') }}">
                    <i class="ni ni-fat-remove"></i>
                </button>
            </form>
        </td>
    </tr>
@endforeach
@endsection
