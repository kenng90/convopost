@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Rule') }}</th>
    <th>{{ __('Service / event') }}</th>
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
            <td>
                @if ($item->event_id)
                    {{ $item->event?->title ?? __('Deleted event') }}
                @else
                    {{ $item->source?->name ?? ($item->source_id ? __('Archived service') : __('All services')) }}
                @endif
            </td>
            <td>
                @if ($item->type == 1)
                    {{ $item->event_id ? __('Before event') : __('Before appointment') }}
                @else
                    {{ $item->event_id ? __('After event') : __('After appointment') }}
                @endif
                · {{ $item->time }} {{ __($item->time_type) }}
            </td>
            <td>
                @if ($item->isServiceManaged() && $item->source_id && $item->source && ! $item->source->trashed())
                    <span class="badge badge-info">{{ __('Service') }}</span>
                    <a href="{{ route('reminders.sources.edit', ['source' => $item->source_id]) }}" class="small d-block mt-1">
                        {{ __('Edit on :service', ['service' => $item->source->name]) }}
                    </a>
                @elseif ($item->isServiceManaged() && $item->event_id && $item->event)
                    <span class="badge badge-info">{{ __('Event') }}</span>
                    <a href="{{ route('reminders.events.edit', ['event' => $item->event_id]) }}" class="small d-block mt-1">
                        {{ __('Edit on :event', ['event' => $item->event->title]) }}
                    </a>
                @elseif ($item->isOrphanedManagedRule())
                    <span class="badge badge-warning">{{ __('Orphaned') }}</span>
                    <span class="small d-block mt-1 text-muted">{{ __('Linked service or event was removed. You can delete this rule.') }}</span>
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
                @if ($item->canDeleteFromList())
                    <a href="{{ route('reminders.reminders.delete',['reminder'=>$item->id]) }}" class="btn btn-danger btn-sm" onclick="return confirm('{{ __('Delete this reminder rule?') }}')">
                        <i class="ni ni-fat-remove"></i>
                    </a>
                @else
                    <span class="text-muted small" title="{{ __('Edit client notifications on the service or event form.') }}">
                        <i class="ni ni-lock-circle-open"></i>
                    </span>
                @endif
            </td>
        </tr>
    @endforeach
@endsection
