@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4 mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">{{ __('Collections') }}</h1>
                    <small class="text-muted">{{ $company->name }} · {{ $openCount }} {{ __('open') }}</small>
                </div>
                <div class="d-flex gap-2">
                    @if($accessibleCompanies->count() > 1)
                        <form method="GET" id="companyForm">
                            <select name="company_id" class="form-control form-control-sm" onchange="document.getElementById('companyForm').submit();">
                                @foreach($accessibleCompanies as $comp)
                                    <option value="{{ $comp->id }}" @selected($comp->id == $currentCompanyId)>{{ $comp->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <a href="{{ route('reports.reconciliation') }}" class="btn btn-light btn-sm">{{ __('Reconciliation') }}</a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <input type="hidden" name="company_id" value="{{ $currentCompanyId }}">
                <input type="search" name="q" class="form-control mr-2 mb-2" placeholder="{{ __('Invoice, name, phone') }}" value="{{ $filters['q'] }}">
                <select name="status" class="form-control mr-2 mb-2">
                    <option value="">{{ __('Open statuses') }}</option>
                    @foreach(['due','requested','pending_pin','failed','chasing','partial','unmatched'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <select name="source" class="form-control mr-2 mb-2">
                    <option value="">{{ __('All sources') }}</option>
                    @foreach(['catalog','booking','flow','action_agent'] as $source)
                        <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $source }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary mb-2" type="submit">{{ __('Filter') }}</button>
            </form>
        </div>
    </div>

    <div class="card shadow">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Next action') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td>
                                <strong>{{ $invoice->invoice_number }}</strong><br>
                                <small class="text-muted">{{ $invoice->getPaymentSource() }}</small>
                            </td>
                            <td>
                                {{ $invoice->customer_name }}<br>
                                <small>{{ $invoice->customer_phone }}</small>
                            </td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                            <td>{{ $invoice->collection_status ?: $invoice->status }}</td>
                            <td>{{ optional($invoice->next_chase_at)->diffForHumans() ?? '—' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('collections.retry', $invoice->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="company_id" value="{{ $currentCompanyId }}">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('Retry') }}</button>
                                </form>
                                <form method="POST" action="{{ route('collections.match', $invoice->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="company_id" value="{{ $currentCompanyId }}">
                                    <input type="text" name="receipt_number" class="form-control form-control-sm d-inline-block" style="width:140px" placeholder="{{ __('Receipt') }}" required>
                                    <button class="btn btn-sm btn-success" type="submit">{{ __('Match') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('No open collections.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $invoices->links() }}</div>
    </div>
</div>
@endsection
