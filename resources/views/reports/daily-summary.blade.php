@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-calendar-day mr-2"></i>Daily Summary Report
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
                <a href="{{ route('reports.daily-summary') }}" class="btn btn-secondary mb-2 ml-2">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
                <a href="{{ route('reports.daily-summary') }}?{{ http_build_query(array_merge(request()->all(), ['format' => 'csv'])) }}" class="btn btn-success mb-2 ml-2">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            </form>
        </div>
    </div>

    <!-- Daily Summary Table -->
    <div class="card shadow">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-table mr-2"></i>Daily Activity Summary
            </h6>
        </div>
        <div class="card-body">
            @if(empty($report['daily_summary']) || count($report['daily_summary']) === 0)
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle mr-2"></i>No data found for the selected date range.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Invoices Created</th>
                                <th>Invoice Amount</th>
                                <th>Transactions</th>
                                <th colspan="3" class="text-center">Transaction Breakdown</th>
                                <th>Total Transaction Amount</th>
                                <th>Successful Amount</th>
                            </tr>
                            <tr class="bg-light">
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th>✓ Successful</th>
                                <th>⏳ Pending</th>
                                <th>✕ Failed</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['daily_summary'] as $day)
                                <tr @if($day['invoices_created'] == 0 && $day['transactions_count'] == 0) class="table-light text-muted" @endif>
                                    <td>
                                        <strong>{{ \Carbon\Carbon::parse($day['date'])->format('D, M d, Y') }}</strong>
                                    </td>
                                    <td>
                                        @if($day['invoices_created'] > 0)
                                            <span class="badge badge-info">{{ $day['invoices_created'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['invoices_amount'] > 0)
                                            <strong class="text-primary">KES {{ number_format($day['invoices_amount'], 2) }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['transactions_count'] > 0)
                                            <span class="badge badge-primary">{{ $day['transactions_count'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['successful'] > 0)
                                            <span class="badge badge-success">{{ $day['successful'] }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['pending'] > 0)
                                            <span class="badge badge-warning">{{ $day['pending'] }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['failed'] > 0)
                                            <span class="badge badge-danger">{{ $day['failed'] }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['total_amount'] > 0)
                                            <strong class="text-primary">KES {{ number_format($day['total_amount'], 2) }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($day['successful_amount'] > 0)
                                            <strong class="text-success">KES {{ number_format($day['successful_amount'], 2) }}</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td>TOTAL</td>
                                <td>{{ collect($report['daily_summary'])->sum('invoices_created') }}</td>
                                <td>KES {{ number_format(collect($report['daily_summary'])->sum('invoices_amount'), 2) }}</td>
                                <td>{{ collect($report['daily_summary'])->sum('transactions_count') }}</td>
                                <td>{{ collect($report['daily_summary'])->sum('successful') }}</td>
                                <td>{{ collect($report['daily_summary'])->sum('pending') }}</td>
                                <td>{{ collect($report['daily_summary'])->sum('failed') }}</td>
                                <td>KES {{ number_format(collect($report['daily_summary'])->sum('total_amount'), 2) }}</td>
                                <td>KES {{ number_format(collect($report['daily_summary'])->sum('successful_amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Daily Statistics -->
    <div class="row mt-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h6 class="text-muted">Average Daily Invoices</h6>
                    <h3 class="text-primary">
                        @if(!empty($report['daily_summary']) && count($report['daily_summary']) > 0)
                            {{ number_format(collect($report['daily_summary'])->sum('invoices_created') / count($report['daily_summary']), 1) }}
                        @else
                            0
                        @endif
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h6 class="text-muted">Average Daily Transactions</h6>
                    <h3 class="text-info">
                        @if(!empty($report['daily_summary']) && count($report['daily_summary']) > 0)
                            {{ number_format(collect($report['daily_summary'])->sum('transactions_count') / count($report['daily_summary']), 1) }}
                        @else
                            0
                        @endif
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h6 class="text-muted">Average Daily Collection</h6>
                    <h3 class="text-success">
                        @if(!empty($report['daily_summary']) && count($report['daily_summary']) > 0)
                            KES {{ number_format(collect($report['daily_summary'])->sum('successful_amount') / count($report['daily_summary']), 2) }}
                        @else
                            KES 0.00
                        @endif
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h6 class="text-muted">Days with Activity</h6>
                    <h3 class="text-warning">
                        {{ count(collect($report['daily_summary'])->filter(fn ($d) => $d['transactions_count'] > 0)) }}
                    </h3>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .table-light {
        opacity: 0.7;
    }

    .bg-light {
        background-color: #f8f9fa !important;
    }
</style>
@endsection
