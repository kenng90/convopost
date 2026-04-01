@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-balance-scale mr-2"></i>Reconciliation Report
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
                <button type="submit" class="btn btn-primary mb-2">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="{{ route('reports.reconciliation') }}" class="btn btn-secondary mb-2 ml-2">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
                <a href="{{ route('reports.reconciliation') }}?{{ http_build_query(array_merge(request()->all(), ['format' => 'csv'])) }}" class="btn btn-success mb-2 ml-2">
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
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Reconciled</div>
                    <div class="h3 mb-0">{{ $report['summary']['reconciled'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Discrepancies</div>
                    <div class="h3 mb-0">{{ $report['summary']['with_discrepancies'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-left-info shadow h-100">
                <div class="card-body">
                    <div class="small text-muted font-weight-bold text-uppercase mb-1">Pending</div>
                    <div class="h3 mb-0">{{ $report['summary']['pending'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliation Rate -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-muted">Reconciliation Rate</p>
                            <h2 class="text-success mb-0">{{ $report['summary']['reconciliation_rate'] }}%</h2>
                            <small class="text-muted">{{ $report['summary']['reconciled'] }} of {{ $report['summary']['total_transactions'] }} transactions matched</small>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted">Total Amount</p>
                            <h4 class="text-primary">KES {{ number_format($report['summary']['total_amount'], 2) }}</h4>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted">Discrepancy Amount</p>
                            <h4 class="text-danger">KES {{ number_format($report['summary']['discrepancy_amount'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciled Transactions -->
    @if(!empty($report['reconciled']))
        <div class="card shadow mb-4">
            <div class="card-header bg-light bg-success py-3">
                <h6 class="m-0 font-weight-bold text-white">
                    <i class="fas fa-check mr-2"></i>Reconciled Transactions ({{ count($report['reconciled']) }})
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Amount</th>
                                <th>M-Pesa Receipt</th>
                                <th>Status</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['reconciled'] as $tx)
                                <tr>
                                    <td><strong>{{ $tx['invoice_number'] }}</strong></td>
                                    <td>KES {{ number_format($tx['amount'], 2) }}</td>
                                    <td><code>{{ $tx['receipt_number'] ?? 'N/A' }}</code></td>
                                    <td>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check mr-1"></i>Matched
                                        </span>
                                    </td>
                                    <td class="small">{{ $tx['created_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Transactions with Discrepancies -->
    @if(!empty($report['discrepancies']))
        <div class="card shadow mb-4">
            <div class="card-header bg-light bg-warning py-3">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Transactions with Discrepancies ({{ count($report['discrepancies']) }})
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Issues</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['discrepancies'] as $tx)
                                <tr class="table-warning">
                                    <td><strong>{{ $tx['invoice_number'] }}</strong></td>
                                    <td>KES {{ number_format($tx['amount'], 2) }}</td>
                                    <td>
                                        <span class="badge badge-danger">
                                            @if($tx['reconciliation_status'] === 'discrepancy')
                                                <i class="fas fa-exclamation-circle mr-1"></i>Discrepancy
                                            @else
                                                <i class="fas fa-times mr-1"></i>Failed
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        @if(isset($tx['issues']) && !empty($tx['issues']))
                                            <ul class="mb-0 small">
                                                @foreach($tx['issues'] as $issue)
                                                    <li>{{ $issue }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-muted">{{ $tx['failure_reason'] ?? 'No details' }}</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $tx['created_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Pending Transactions -->
    @if(!empty($report['pending']))
        <div class="card shadow">
            <div class="card-header bg-light bg-info py-3">
                <h6 class="m-0 font-weight-bold text-white">
                    <i class="fas fa-clock mr-2"></i>Pending Transactions ({{ count($report['pending']) }})
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">These transactions are waiting for M-Pesa callback confirmation.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Checkout Request ID</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['pending'] as $tx)
                                <tr>
                                    <td><strong>{{ $tx['invoice_number'] }}</strong></td>
                                    <td>{{ $tx['customer_phone'] }}</td>
                                    <td>KES {{ number_format($tx['amount'], 2) }}</td>
                                    <td>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock mr-1"></i>Pending
                                        </span>
                                    </td>
                                    <td><code class="small">{{ substr($tx['checkout_request_id'], 0, 20) }}...</code></td>
                                    <td class="small">{{ $tx['created_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if(empty($report['reconciled']) && empty($report['discrepancies']) && empty($report['pending']))
        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle mr-2"></i>No transactions found for the selected filters.
        </div>
    @endif
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

    .border-left-info {
        border-left: 0.25rem solid #17a2b8 !important;
    }

    .bg-success {
        background-color: #28a745 !important;
    }

    .bg-warning {
        background-color: #ffc107 !important;
    }

    .bg-info {
        background-color: #17a2b8 !important;
    }

    .table-warning {
        background-color: #fff3cd !important;
    }
</style>
@endsection
