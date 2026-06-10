<div class="modal fade" id="invoiceModal{{ $payment['invoice_id'] }}" tabindex="-1" role="dialog"
    aria-labelledby="invoiceModalLabel{{ $payment['invoice_id'] }}" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel{{ $payment['invoice_id'] }}">
                    Invoice {{ $payment['invoice_number'] }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">Status</span>
                    @if($payment['status'] === 'paid')
                        <span class="badge badge-success">Paid</span>
                    @elseif($payment['status'] === 'sent')
                        <span class="badge badge-info">Sent</span>
                    @elseif($payment['status'] === 'draft')
                        <span class="badge badge-secondary">Draft</span>
                    @else
                        <span class="badge badge-danger">{{ ucfirst($payment['status']) }}</span>
                    @endif
                </div>

                <dl class="row mb-4">
                    <dt class="col-sm-4">Customer</dt>
                    <dd class="col-sm-8">{{ $payment['customer_name'] ?? '—' }}</dd>

                    <dt class="col-sm-4">Phone</dt>
                    <dd class="col-sm-8">{{ $payment['customer_phone'] }}</dd>

                    @if($payment['customer_email'])
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8">{{ $payment['customer_email'] }}</dd>
                    @endif

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8">{{ $payment['created_at'] }}</dd>

                    @if($payment['sent_at'])
                        <dt class="col-sm-4">Sent</dt>
                        <dd class="col-sm-8">{{ $payment['sent_at'] }}</dd>
                    @endif

                    @if($payment['paid_at'])
                        <dt class="col-sm-4">Paid</dt>
                        <dd class="col-sm-8">{{ $payment['paid_at'] }}</dd>
                    @endif
                </dl>

                @if(!empty($payment['items']))
                    <h6 class="font-weight-bold mb-2">Line Items</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payment['items'] as $item)
                                    <tr>
                                        <td>
                                            {{ $item['title'] ?? 'Item' }}
                                            @if(!empty($item['variant']))
                                                <div class="text-muted small">{{ $item['variant'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                                        <td class="text-right">
                                            {{ $payment['currency'] ?? 'KES' }}
                                            {{ number_format($item['total'] ?? 0, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="row mb-4">
                    <div class="col-md-4">
                        <p class="text-muted small mb-1">Invoice Amount</p>
                        <p class="font-weight-bold mb-0">
                            {{ $payment['currency'] ?? 'KES' }}
                            {{ number_format($payment['invoice_amount'], 2) }}
                        </p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted small mb-1">Total Paid</p>
                        <p class="font-weight-bold text-success mb-0">
                            {{ $payment['currency'] ?? 'KES' }}
                            {{ number_format($payment['total_paid'], 2) }}
                        </p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted small mb-1">Remaining</p>
                        <p class="font-weight-bold @if($payment['remaining'] == 0) text-success @else text-warning @endif mb-0">
                            {{ $payment['currency'] ?? 'KES' }}
                            {{ number_format($payment['remaining'], 2) }}
                        </p>
                    </div>
                </div>

                <h6 class="font-weight-bold mb-2">Payment History</h6>
                @if(empty($payment['payment_records']))
                    <p class="text-muted small mb-0">No payment attempts recorded.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payment['payment_records'] as $record)
                                    <tr>
                                        <td class="small">{{ $record['created_at'] }}</td>
                                        <td>
                                            {{ $payment['currency'] ?? 'KES' }}
                                            {{ number_format($record['amount'], 2) }}
                                        </td>
                                        <td>
                                            @if($record['status'] === 'success')
                                                <span class="badge badge-success">Success</span>
                                            @elseif($record['status'] === 'pending')
                                                <span class="badge badge-warning">Pending</span>
                                            @else
                                                <span class="badge badge-danger">{{ ucfirst($record['status']) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($record['mpesa_receipt_number'])
                                                <code class="small">{{ $record['mpesa_receipt_number'] }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
