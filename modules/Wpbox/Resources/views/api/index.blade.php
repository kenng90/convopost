@extends('general.index', $setup)

@section('contenttop')
    <div class="px-4 pb-3">
        <div class="alert alert-info mb-0">
            <strong>{{ __('How API campaigns work') }}</strong>
            <p class="mb-2">
                {{ __('Create a template once, then trigger it by campaign ID with a phone number and optional data payload. Messages are queued and sent by the scheduler.') }}
            </p>
            <code class="d-block">POST {{ $sendEndpoint }}</code>
            <small class="text-muted">{{ __('Required: token, campaign_id, phone. Optional: data (object), send_now (bool).') }}</small>
        </div>
    </div>
@endsection

@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Campaign ID') }}</th>
    <th>{{ __('Template') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection

@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>
                <code id="campaign-id-{{ $item->id }}">{{ $item->id }}</code>
                <button type="button" class="btn btn-sm btn-link p-0 ml-1"
                        onclick="navigator.clipboard.writeText('{{ $item->id }}')">
                    {{ __('Copy') }}
                </button>
            </td>
            <td>{{ $item->template->name ?? __('Unknown template') }}</td>
            <td>
                @if ($item->is_active && $item->status !== \Modules\Wpbox\Models\Campaign::STATUS_INACTIVE)
                    <span class="badge badge-success">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                @endif
            </td>
            <td class="d-flex flex-wrap" style="gap: 0.25rem;">
                <a href="{{ route('campaigns.show', $item->id) }}" class="btn btn-info btn-sm">{{ __('Details') }}</a>
                <a href="{{ route('wpbox.api.edit', $item->id) }}" class="btn btn-primary btn-sm">{{ __('Edit') }}</a>
                <a href="{{ route('wpbox.api.clone', $item->id) }}" class="btn btn-outline-primary btn-sm">{{ __('Clone') }}</a>
                <a href="{{ route('wpbox.api.toggle', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                    {{ $item->is_active && $item->status !== \Modules\Wpbox\Models\Campaign::STATUS_INACTIVE ? __('Deactivate') : __('Activate') }}
                </a>
                <a href="{{ route('campaigns.delete', $item->id) }}" class="btn btn-danger btn-sm"
                   onclick="return confirm('{{ __('Are you sure you want to delete this item?') }}')">
                    {{ __('Delete') }}
                </a>
            </td>
        </tr>
    @endforeach
@endsection
