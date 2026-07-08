@extends('layouts.app', ['title' => __('ConvoConnect SMS')])
@section('content')
<div class="header pb-6 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="mb-0">📲 {{ __('ConvoConnect SMS') }}</h1>
                    <p class="text-muted mb-0">{{ __('Manage per-tenant sub-accounts, Sender IDs, and credentials.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--6">
    @include('partials.flash')

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card card-stats">
                <div class="card-body">
                    <span class="h2 font-weight-bold mb-0">{{ $platformEnabled ? __('Enabled') : __('Disabled') }}</span>
                    <span class="text-muted d-block">{{ __('ConvoConnect integration') }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stats">
                <div class="card-body">
                    <span class="h2 font-weight-bold mb-0">{{ $resellerConfigured ? __('Ready') : __('Missing') }}</span>
                    <span class="text-muted d-block">{{ __('Platform credentials') }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stats">
                <div class="card-body">
                    <span class="h2 font-weight-bold mb-0">{{ $companies->total() }}</span>
                    <span class="text-muted d-block">{{ __('Organizations listed') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Organization name') }}</label>
                    <input type="text" name="name" value="{{ $filters['name'] }}" class="form-control" placeholder="{{ __('Search…') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Sender ID status') }}</label>
                    <select name="sender_status" class="form-control">
                        <option value="">{{ __('All') }}</option>
                        <option value="not_provisioned" @selected($filters['sender_status'] === 'not_provisioned')>{{ __('Not provisioned') }}</option>
                        <option value="pending_approval" @selected($filters['sender_status'] === 'pending_approval')>{{ __('Pending approval') }}</option>
                        <option value="approved" @selected($filters['sender_status'] === 'approved')>{{ __('Approved') }}</option>
                        <option value="request_failed" @selected($filters['sender_status'] === 'request_failed')>{{ __('Request failed') }}</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.convoconnect.index') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-items-center">
                <thead class="thead-light">
                    <tr>
                        <th>{{ __('Organization') }}</th>
                        <th>{{ __('Owner') }}</th>
                        <th>{{ __('Sender ID') }}</th>
                        <th>{{ __('Sender status') }}</th>
                        <th>{{ __('SMS ready') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.convoconnect.show', $row['company_id']) }}"><strong>{{ $row['company_name'] }}</strong></a>
                                <div class="text-muted small">{{ $row['subdomain'] }}</div>
                            </td>
                            <td>
                                <div>{{ $row['owner_name'] }}</div>
                                <div class="text-muted small">{{ $row['owner_email'] }}</div>
                            </td>
                            <td>{{ $row['sender_id'] ?: '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $presenter->senderStatusBadgeClass($row['sender_status']) }}">
                                    {{ str_replace('_', ' ', $row['sender_status']) }}
                                </span>
                            </td>
                            <td>
                                @if($row['sms_ready'])
                                    <span class="badge badge-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.convoconnect.show', $row['company_id']) }}" class="btn btn-sm btn-primary">{{ __('Details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('No organizations found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($companies->hasPages())
            <div class="card-footer">
                {{ $companies->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
