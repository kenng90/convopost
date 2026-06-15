@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Email') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('Modules') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->user->name }}</td>
            <td>{{ $item->user->email }}</td>
            <td>
                <span class="badge badge-{{ $item->status === 'active' ? 'success' : 'warning' }}">
                    {{ ucfirst($item->status) }}
                </span>
            </td>
            <td>{{ $item->modules->pluck('module_alias')->join(', ') ?: '—' }}</td>
            <?php $param = [$setup['parameter_name'] => $item->id]; ?>
            <td>
                <a href="{{ route($setup['webroute_path'].'edit', $param) }}" class="btn btn-primary btn-sm">{{ __('crud.edit') }}</a>
                <a href="{{ route($setup['webroute_path'].'delete', $param) }}" class="btn btn-danger btn-sm">{{ __('crud.delete') }}</a>
                <a href="{{ route($setup['webroute_path'].'loginas', $param) }}" class="btn btn-success btn-sm">{{ __('Login as') }}</a>
            </td>
        </tr>
    @endforeach
@endsection
@section('footer')
    <div class="mt-3">
        <a href="{{ $templatesLink }}" class="btn btn-outline-primary btn-sm">{{ __('Role templates') }}</a>
    </div>
@endsection
