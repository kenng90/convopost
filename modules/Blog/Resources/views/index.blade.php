@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Title') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('Date') }}</th>
    <th>{{ __('Actions') }}</th>
@endsection
@section('tbody')
@foreach ($setup['items'] as $item)
    
    <tr>
        <td style="width: 35%">
            <div class="d-flex align-items-center">
                @if($item->featured_image)
                    <img src="{{ $item->featured_image }}" class="me-2  mx-2 rounded" style=" height: 40px; object-fit: cover;">
                @endif
                <div>
                    <a href="{{ route('blogging.edit', ['item' => $item->id]) }}" class="text-decoration-none">
                        <strong>{{ $item->title }}</strong>
                    </a>
                    @if($item->excerpt)
                        <div class="text-muted small">{{ Str::limit($item->excerpt, 100) }}</div>
                    @endif
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-{{ $item->status === 'published' ? 'success' : 'warning' }}">
                {{ ucfirst($item->status) }}
            </span>
        </td>

        <td>
            <div>{{ $item->created_at->format('M d, Y') }}</div>
            <small class="text-muted">{{ $item->created_at->format('h:i A') }}</small>
        </td>
        <td>
            <a href="{{ route('blogging.edit', ['item' => $item->id]) }}" class="btn btn-sm btn-primary">
                {{ __('Edit') }}
            </a>
            <a href="{{ route('blogging.clone', ['item' => $item->id]) }}" class="btn btn-sm btn-info">
                {{ __('Clone') }}
            </a>
            <a href="{{ route('blogging.delete', ['item' => $item->id]) }}" 
               class="btn btn-sm btn-danger" 
               onclick="return confirm('{{ __('Are you sure you want to delete this post?') }}')">
                {{ __('Delete') }}
            </a>
        </td>
    </tr> 
@endforeach
@endsection

@push('styles')
<style>
    .table > :not(caption) > * > * {
        padding: 1rem 0.75rem;
        vertical-align: middle;
    }
    .badge {
        padding: 0.5em 0.75em;
        font-weight: 500;
    }
    .btn-group {
        gap: 0.25rem;
    }
    .btn-group .btn {
        border-radius: 0.25rem !important;
    }
    .progress {
        background-color: #e9ecef;
        border-radius: 3px;
    }
</style>
@endpush
