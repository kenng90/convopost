@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-3">
                        <i class="fas fa-chart-bar mr-2"></i>Reports & Analytics
                    </h1>
                    <p class="text-muted">View transactions, payments, and reconciliation reports for {{ $company->name }}</p>
                </div>
                @if($accessibleCompanies->count() > 1)
                    <div style="min-width: 250px;">
                        <form method="GET" id="companyForm">
                            <label class="small font-weight-bold text-muted mb-2 d-block">SELECT COMPANY</label>
                            <select name="company_id" class="form-control form-control-sm" onchange="document.getElementById('companyForm').submit();">
                                @foreach($accessibleCompanies as $comp)
                                    <option value="{{ $comp->id }}" @selected($comp->id == $currentCompanyId)>
                                        {{ $comp->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="row mb-4">
        <!-- Transactions Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="text-primary small font-weight-bold text-uppercase mb-1">
                        <i class="fas fa-exchange-alt mr-2"></i>Transactions
                    </div>
                    <div class="h3 mb-0">View All</div>
                    <p class="text-muted small mt-2">M-Pesa payments and transaction history</p>
                </div>
                <a href="{{ route('reports.transactions') }}" class="card-footer bg-light text-primary stretched-link">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Payments Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="text-success small font-weight-bold text-uppercase mb-1">
                        <i class="fas fa-credit-card mr-2"></i>Payments
                    </div>
                    <div class="h3 mb-0">View All</div>
                    <p class="text-muted small mt-2">Invoice payment status and collection</p>
                </div>
                <a href="{{ route('reports.payments') }}" class="card-footer bg-light text-success stretched-link">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Reconciliation Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="text-warning small font-weight-bold text-uppercase mb-1">
                        <i class="fas fa-balance-scale mr-2"></i>Reconciliation
                    </div>
                    <div class="h3 mb-0">View All</div>
                    <p class="text-muted small mt-2">Match transactions with M-Pesa records</p>
                </div>
                <a href="{{ route('reports.reconciliation') }}" class="card-footer bg-light text-warning stretched-link">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Daily Summary Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-left-info shadow h-100">
                <div class="card-body">
                    <div class="text-info small font-weight-bold text-uppercase mb-1">
                        <i class="fas fa-calendar-day mr-2"></i>Daily Summary
                    </div>
                    <div class="h3 mb-0">View All</div>
                    <p class="text-muted small mt-2">Day-by-day activity overview</p>
                </div>
                <a href="{{ route('reports.daily-summary') }}" class="card-footer bg-light text-info stretched-link">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Start -->
    <div class="card shadow mb-4">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-rocket mr-2"></i>Quick Start
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="font-weight-bold mb-3">Popular Reports</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="{{ route('reports.transactions') }}?start_date={{ now()->subDays(7)->format('Y-m-d') }}&end_date={{ now()->format('Y-m-d') }}" class="text-decoration-none">
                                <i class="fas fa-arrow-right text-primary mr-2"></i>Last 7 Days Transactions
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="{{ route('reports.payments') }}?invoice_status=sent" class="text-decoration-none">
                                <i class="fas fa-arrow-right text-success mr-2"></i>Pending Invoices
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="{{ route('reports.reconciliation') }}" class="text-decoration-none">
                                <i class="fas fa-arrow-right text-warning mr-2"></i>Unreconciled Transactions
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reports.daily-summary') }}?start_date={{ now()->subDays(30)->format('Y-m-d') }}&end_date={{ now()->format('Y-m-d') }}" class="text-decoration-none">
                                <i class="fas fa-arrow-right text-info mr-2"></i>Last 30 Days Summary
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="font-weight-bold mb-3">Report Features</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success mr-2"></i>Filter by date range
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success mr-2"></i>Filter by status
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success mr-2"></i>Export to CSV
                        </li>
                        <li>
                            <i class="fas fa-check text-success mr-2"></i>Real-time data
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Documentation -->
    <div class="card shadow">
        <div class="card-header bg-light py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-info-circle mr-2"></i>Report Descriptions
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="font-weight-bold mb-2">Transactions Report</h6>
                    <p class="text-muted small mb-4">
                        Shows all M-Pesa STK push payment attempts with their status (pending, successful, failed). 
                        Includes transaction amounts, M-Pesa receipt numbers, and timestamps.
                    </p>

                    <h6 class="font-weight-bold mb-2">Payments Report</h6>
                    <p class="text-muted small mb-4">
                        Displays invoice payment status including total invoice amount, amount paid, remaining balance, 
                        and collection rate. Shows payment history per invoice.
                    </p>
                </div>
                <div class="col-md-6">
                    <h6 class="font-weight-bold mb-2">Reconciliation Report</h6>
                    <p class="text-muted small mb-4">
                        Validates that local payment records match M-Pesa responses. Identifies discrepancies such as 
                        amount mismatches, missing receipts, and duplicate payments.
                    </p>

                    <h6 class="font-weight-bold mb-2">Daily Summary Report</h6>
                    <p class="text-muted small mb-4">
                        Provides day-by-day overview of invoices created, transactions processed, and amounts collected. 
                        Useful for tracking daily business performance.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
    }

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

    .text-primary {
        color: #007bff !important;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-warning {
        color: #ffc107 !important;
    }

    .text-info {
        color: #17a2b8 !important;
    }
</style>
@endsection
