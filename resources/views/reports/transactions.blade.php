@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4 mt-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-exchange-alt mr-2"></i>Transactions Report
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
                    <label for="status" class="mr-2">Status:</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" @if($filters['status'] === 'pending') selected @endif>Pending</option>
                        <option value="success" @if($filters['status'] === 'success') selected @endif>Successful</option>
                        <option value="failed" @if($filters['status'] === 'failed') selected @endif>Failed</option>
                        <option value="cancelled" @if($filters['status'] === 'cancelled') selected @endif>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary mb-2">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="{{ route('reports.transactions') }}" class="btn btn-secondary mb-2 ml-2">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
                <a href="{{ route('reports.transactions') }}?{{ http_build_query(array_merge(request()->all(), ['format' => 'csv'])) }}" class="btn btn-success mb-2 ml-2">
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
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Total Transactions</div>
                    <div class="h3 mb-0">{{ $report['summary']['total_transactions'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Successful</div>
                    <div class="h3 mb-0">{{ $report['summary']['successful'] }}</div>
                    <small class="text-muted">KES {{ number_format($report['summary']['successful_amount'], 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Pending</div>
                    <div class="h3 mb-0">{{ $report['summary']['pending'] }}</div>
                    <small class="text-muted">KES {{ number_format($report['summary']['pending_amount'], 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-danger shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Failed</div>
                    <div class="h3 mb-0">{{ $report['summary']['failed'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="text-muted">Total Amount</p>
                                <h4 class="text-primary">KES {{ number_format($report['summary']['total_amount'], 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="text-muted">Success Rate</p>
                                <h4 class="text-success">{{ $report['summary']['success_rate'] }}%</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list mr-2"></i>Transactions ({{ $report['total_count'] }})
            </h6>
        </div>
        <div class="card-body">
            @if(empty($report['transactions']) || count($report['transactions']) === 0)
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle mr-2"></i>No transactions found for the selected filters.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>M-Pesa Receipt</th>
                                <th>Created Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['transactions'] as $transaction)
                                <tr>
                                    <td>
                                        <a href="{{ route('reports.payments') }}" class="font-weight-bold text-dark">
                                            {{ $transaction['invoice_number'] }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="small">{{ $transaction['customer_name'] }}</div>
                                        <div class="text-muted small">{{ $transaction['customer_phone'] }}</div>
                                    </td>
                                    <td>KES {{ number_format($transaction['amount'], 2) }}</td>
                                    <td>
                                        @if($transaction['status'] === 'success')
                                            <span class="badge badge-success">
                                                <i class="fas fa-check mr-1"></i>Successful
                                            </span>
                                        @elseif($transaction['status'] === 'pending')
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock mr-1"></i>Pending
                                            </span>
                                        @elseif($transaction['status'] === 'failed')
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times mr-1"></i>Failed
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">
                                                {{ ucfirst($transaction['status']) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($transaction['mpesa_receipt_number'])
                                            <code class="text-dark">{{ $transaction['mpesa_receipt_number'] }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $transaction['created_at'] }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#detailsModal{{ $transaction['payment_id'] }}">
                                            View Details
                                        </button>
                                    </td>
                                </tr>

                                <!-- Details Modal -->
                                <div class="modal fade" id="detailsModal{{ $transaction['payment_id'] }}" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Transaction Details - {{ $transaction['invoice_number'] }}</h5>
                                                <button type="button" class="close" data-dismiss="modal">
                                                    <span>&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row">
                                                    <dt class="col-sm-4">Payment ID:</dt>
                                                    <dd class="col-sm-8">{{ $transaction['payment_id'] }}</dd>

                                                    <dt class="col-sm-4">Invoice Number:</dt>
                                                    <dd class="col-sm-8">{{ $transaction['invoice_number'] }}</dd>

                                                    <dt class="col-sm-4">Customer:</dt>
                                                    <dd class="col-sm-8">{{ $transaction['customer_name'] }} ({{ $transaction['customer_phone'] }})</dd>

                                                    <dt class="col-sm-4">Amount:</dt>
                                                    <dd class="col-sm-8">KES {{ number_format($transaction['amount'], 2) }}</dd>

                                                    <dt class="col-sm-4">Status:</dt>
                                                    <dd class="col-sm-8">
                                                        <span class="badge badge-{{ $transaction['status'] === 'success' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger') }}">
                                                            {{ ucfirst($transaction['status']) }}
                                                        </span>
                                                    </dd>

                                                    <dt class="col-sm-4">M-Pesa Checkout ID:</dt>
                                                    <dd class="col-sm-8"><code>{{ $transaction['mpesa_checkout_request_id'] }}</code></dd>

                                                    <dt class="col-sm-4">M-Pesa Receipt:</dt>
                                                    <dd class="col-sm-8"><code>{{ $transaction['mpesa_receipt_number'] ?? 'N/A' }}</code></dd>

                                                    <dt class="col-sm-4">Created:</dt>
                                                    <dd class="col-sm-8">{{ $transaction['created_at'] }}</dd>

                                                    <dt class="col-sm-4">Updated:</dt>
                                                    <dd class="col-sm-8">{{ $transaction['updated_at'] }}</dd>
                                                </dl>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
</style>
@endsection
