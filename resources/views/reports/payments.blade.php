@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4 mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-credit-card mr-2"></i>Payments Report
                    </h1>
                    <small class="text-muted">{{ $company->name }}</small>
                </div>
                <div class="d-flex gap-2">
                    @if($accessibleCompanies->count() > 1)
                        <form method="GET" id="companyForm" class="d-flex align-items-end gap-2">
                            <div>
                                <label class="small font-weight-bold text-muted d-block mb-1">SELECT COMPANY</label>
                                <select name="company_id" class="form-control form-control-sm" onchange="document.getElementById('companyForm').submit();">
                                    @foreach($accessibleCompanies as $comp)
                                        <option value="{{ $comp->id }}" @selected($comp->id == $currentCompanyId)>
                                            {{ $comp->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    @endif
                    <a href="{{ route('reports.dashboard') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-filter mr-2"></i>Filters
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="form-inline" id="filterForm">
                <div class="form-group mr-3 mb-2">
                    <label for="start_date" class="mr-2">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $filters['start_date'] }}">
                </div>
                <div class="form-group mr-3 mb-2">
                    <label for="end_date" class="mr-2">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $filters['end_date'] }}">
                </div>
                <div class="form-group mr-3 mb-2">
                    <label for="invoice_status" class="mr-2">Invoice Status:</label>
                    <select id="invoice_status" name="invoice_status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="draft" @if($filters['invoice_status'] === 'draft') selected @endif>Draft</option>
                        <option value="sent" @if($filters['invoice_status'] === 'sent') selected @endif>Sent</option>
                        <option value="paid" @if($filters['invoice_status'] === 'paid') selected @endif>Paid</option>
                        <option value="cancelled" @if($filters['invoice_status'] === 'cancelled') selected @endif>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary mb-2">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="{{ route('reports.payments') }}" class="btn btn-secondary mb-2 ml-2">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
                <a href="{{ route('reports.payments') }}?{{ http_build_query(array_merge(request()->all(), ['format' => 'csv'])) }}" class="btn btn-success mb-2 ml-2">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Total Invoices</div>
                    <div class="h3 mb-0">{{ $report['summary']['total_invoices'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Paid Invoices</div>
                    <div class="h3 mb-0">{{ $report['summary']['paid_invoices'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Partially Paid</div>
                    <div class="h3 mb-0">{{ $report['summary']['partially_paid'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-danger shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Unpaid</div>
                    <div class="h3 mb-0">{{ $report['summary']['unpaid_invoices'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <p class="text-muted">Total Invoice Amount</p>
                    <h4 class="text-primary">KES {{ number_format($report['summary']['total_invoice_amount'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow">
                <div class="card-body">
                    <p class="text-muted">Total Paid</p>
                    <h4 class="text-success">KES {{ number_format($report['summary']['total_paid_amount'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow">
                <div class="card-body">
                    <p class="text-muted">Collection Rate</p>
                    <h4 class="text-info">{{ $report['summary']['collection_rate'] }}%</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card shadow">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list mr-2"></i>Invoices ({{ $report['total_count'] }})
            </h6>
        </div>
        <div class="card-body">
            @if(empty($report['payments']) || count($report['payments']) === 0)
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle mr-2"></i>No invoices found for the selected filters.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Invoice Amount</th>
                                <th>Total Paid</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Payments</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['payments'] as $payment)
                                <tr>
                                    <td>
                                        <strong class="text-dark">
                                            <a href="#"
                                                class="text-dark invoice-preview-trigger"
                                                data-toggle="modal"
                                                data-target="#invoiceModal{{ $payment['invoice_id'] }}"
                                                onclick="event.preventDefault();">
                                                {{ $payment['invoice_number'] }}
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <div class="small">{{ $payment['customer_name'] }}</div>
                                        <div class="text-muted small">{{ $payment['customer_phone'] }}</div>
                                    </td>
                                    <td>KES {{ number_format($payment['invoice_amount'], 2) }}</td>
                                    <td>
                                        <span class="text-success font-weight-bold">
                                            KES {{ number_format($payment['total_paid'], 2) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold @if($payment['remaining'] == 0) text-success @else text-warning @endif">
                                            KES {{ number_format($payment['remaining'], 2) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($payment['status'] === 'paid')
                                            <span class="badge badge-success">
                                                <i class="fas fa-check mr-1"></i>Paid
                                            </span>
                                        @elseif($payment['status'] === 'sent')
                                            <span class="badge badge-info">
                                                <i class="fas fa-paper-plane mr-1"></i>Sent
                                            </span>
                                        @elseif($payment['status'] === 'draft')
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-file mr-1"></i>Draft
                                            </span>
                                        @else
                                            <span class="badge badge-danger">
                                                {{ ucfirst($payment['status']) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small">
                                            <span class="badge badge-success">✓ {{ $payment['successful_payments'] }}</span>
                                            <span class="badge badge-warning">⏳ {{ $payment['pending_payments'] }}</span>
                                            <span class="badge badge-danger">✕ {{ $payment['failed_payments'] }}</span>
                                        </div>
                                    </td>
                                    <td class="small">{{ $payment['created_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach($report['payments'] as $payment)
                    @include('reports.partials.invoice-preview-modal', ['payment' => $payment])
                @endforeach
            @endif
        </div>
    </div>
</div>

<style>
    .border-left-primary {
        border-left: 0.25rem solid #007bff !important;
    }

    .border-left-success {
        border-left: 0.25rem solid #28a745 !important;
    }

    .border-left-warning {
        border-left: 0.25rem solid #ffc107 !important;
    }

    .border-left-danger {
        border-left: 0.25rem solid #dc3545 !important;
    }

    .invoice-preview-trigger {
        text-decoration: none;
        cursor: pointer;
    }

    .invoice-preview-trigger:hover {
        text-decoration: underline;
    }
</style>
@endsection
