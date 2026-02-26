@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Source') }}</th>
    <th>{{ __('Type') }}</th>
    <th>{{ __('Time') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('Analytics') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ $item->source?$item->source->name:__('All')}}</td>
            <td>{{ $item->type == 1 ? __('Before event') : __('After event') }}</td>
            <td>{{ $item->time.' '.__($item->time_type) }}</td>
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

                <!-- Delete -->
                <a href="{{ route('reminders.reminders.delete',['reminder'=>$item->id]) }}" class="btn btn-danger btn-sm">
                    <i class="ni ni ni-fat-remove"></i>
                </a>
            </td>
        </tr> 
    @endforeach
@endsection