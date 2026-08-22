@extends('layouts.app')

@section('admin_title')
    {{ __('Trust & deliverability') }}
@endsection

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h2 class="mb-1">{{ __('Trust & deliverability') }}</h2>
            <p class="text-muted mb-4">{{ __('Consent, CSAT, quality rating, and an audit trail for this workspace.') }}</p>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('Quality rating') }}</p>
                        <p class="h2 mb-0">{{ $quality }}</p>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('Opt-ins') }}</p>
                        <p class="h2 mb-0">{{ $consent['opt_ins'] }}</p>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('Opt-outs') }}</p>
                        <p class="h2 mb-0">{{ $consent['opt_outs'] }}</p>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('CSAT average') }}</p>
                        <p class="h2 mb-0">{{ $csat['average'] ?? '—' }}</p>
                    </div></div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">{{ __('Recent audit log') }}</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('When') }}</th>
                                <th>{{ __('Action') }}</th>
                                <th>{{ __('Subject') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($audit as $row)
                                <tr>
                                    <td>{{ $row->created_at }}</td>
                                    <td>{{ $row->action }}</td>
                                    <td>{{ $row->subject_type }} {{ $row->subject_id }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">{{ __('No audit events yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
