<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invoice') }} {{ $invoice->invoice_number }}</title>
    @include('layouts.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; padding: 2rem 0; }
        .invoice-card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,.08); overflow: hidden; }
        .invoice-header { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; padding: 2rem; }
        .status-paid { color: #16a34a; font-weight: 700; }
        .status-pending { color: #d97706; font-weight: 700; }
    </style>
</head>
<body>
<div class="invoice-card">
    <div class="invoice-header">
        <h1 class="h3 mb-1">{{ $company->name ?? config('app.name') }}</h1>
        <p class="mb-0 opacity-75">{{ __('Invoice') }} #{{ $invoice->invoice_number }}</p>
    </div>
    <div class="p-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small">{{ __('Bill to') }}</h6>
                <p class="mb-0 font-weight-bold">{{ $invoice->customer_name }}</p>
                <p class="mb-0">{{ $invoice->customer_phone }}</p>
                @if($invoice->customer_email)<p class="mb-0">{{ $invoice->customer_email }}</p>@endif
            </div>
            <div class="col-md-6 text-md-right">
                <p class="mb-1"><strong>{{ __('Status') }}:</strong>
                    <span class="{{ $invoice->status === 'paid' ? 'status-paid' : 'status-pending' }}">{{ strtoupper($invoice->status) }}</span>
                </p>
                <p class="mb-1"><strong>{{ __('Date') }}:</strong> {{ $invoice->created_at->format('M d, Y') }}</p>
                @if($invoice->paid_at)
                    <p class="mb-0"><strong>{{ __('Paid') }}:</strong> {{ $invoice->paid_at->format('M d, Y H:i') }}</p>
                @endif
            </div>
        </div>

        @if($invoice->description)
            <p class="text-muted">{{ $invoice->description }}</p>
        @endif

        <table class="table table-sm">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach($invoice->items ?? [] as $item)
                    <tr>
                        <td>{{ $item['title'] ?? $item['name'] ?? __('Item') }}</td>
                        <td class="text-right">{{ $item['quantity'] ?? 1 }}</td>
                        <td class="text-right">{{ number_format($item['total'] ?? $item['price'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">{{ __('Total') }}</th>
                    <th class="text-right">{{ $invoice->currency }} {{ number_format($invoice->amount, 2) }}</th>
                </tr>
            </tfoot>
        </table>

        @if($invoice->status !== 'paid')
            <div class="text-center mt-4">
                <a href="{{ $paymentUrl }}" class="btn btn-primary btn-lg">{{ __('Pay with M-Pesa') }}</a>
            </div>
        @else
            <div class="alert alert-success text-center mb-0">{{ __('This invoice has been paid. Thank you!') }}</div>
        @endif
    </div>
</div>
</body>
</html>
