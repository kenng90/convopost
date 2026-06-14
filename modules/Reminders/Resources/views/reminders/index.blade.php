@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Rule') }}</th>
    <th>{{ __('Service') }}</th>
    <th>{{ __('When') }}</th>
    <th>{{ __('Managed by') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('Analytics') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ $item->source ? $item->source->name : __('All services') }}</td>
            <td>
                {{ $item->type == 1 ? __('Before appointment') : __('After appointment') }}
                · {{ $item->time }} {{ __($item->time_type) }}
            </td>
            <td>
                @if ($item->isServiceManaged() && $item->source_id)
                    <span class="badge badge-info">{{ __('Service') }}</span>
                    <a href="{{ route('reminders.sources.edit', ['source' => $item->source_id]) }}" class="small d-block mt-1">
                        {{ __('Edit on :service', ['service' => $item->source->name]) }}
                    </a>
                @else
                    <span class="badge badge-secondary">{{ __('Manual') }}</span>
                @endif
            </td>
            <td>
                @if($item->status == 1)
                    <span class="badge badge-success">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-secondary">{{ __('Paused') }}</span>
                @endif
            </td>
            <td>
                @if ($item->campaign_id)
                    <a href="{{ route('campaigns.show',$item->campaign_id)}}" class="btn btn-success btn-sm">{{ __('Analytics')}}</a>
                @endif
            </td>
            <td>
                @if ($item->isServiceManaged())
                    <span class="text-muted small" title="{{ __('Edit client notifications on the service form.') }}">
                        <i class="ni ni-lock-circle-open"></i>
                    </span>
                @else
                    <a href="{{ route('reminders.reminders.delete',['reminder'=>$item->id]) }}" class="btn btn-danger btn-sm" onclick="return confirm('{{ __('Delete this reminder rule?') }}')">
                        <i class="ni ni-fat-remove"></i>
                    </a>
                @endif
            </td>
        </tr>
    @endforeach
@endsection
