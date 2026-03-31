<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice['invoice_number'] }} - Payment</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .invoice-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }

        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .invoice-header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 700;
        }

        .invoice-header p {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .invoice-content {
            padding: 30px;
        }

        .invoice-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .invoice-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-label {
            font-size: 12px;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .invoice-items {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-name {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            flex: 1;
        }

        .item-qty {
            color: #6c757d;
            font-size: 13px;
            margin: 0 15px;
        }

        .item-price {
            font-weight: 600;
            color: #333;
            min-width: 70px;
            text-align: right;
        }

        .amount-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .amount-row:last-child {
            margin-bottom: 0;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
        }

        .amount-label {
            color: #6c757d;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-draft {
            background-color: #e7f5ff;
            color: #0066cc;
        }

        .status-paid {
            background-color: #d3f9d8;
            color: #2b8a3e;
        }

        .status-pending {
            background-color: #fff3bf;
            color: #997404;
        }

        .payment-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .payment-method {
            text-align: center;
            margin-bottom: 20px;
        }

        .mpesa-logo {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .pay-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .pay-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .pay-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .payment-info {
            background: #e7f5ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #0066cc;
        }

        .payment-info i {
            margin-right: 10px;
        }

        .alert {
            border-radius: 8px;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-message {
            text-align: center;
            padding: 20px;
            display: none;
        }

        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .invoice-container {
                margin: 20px;
            }

            .invoice-header {
                padding: 20px;
            }

            .invoice-content {
                padding: 20px;
            }

            .invoice-info {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <h1>{{ $invoice['company']['name'] }}</h1>
            <p>Invoice #{{ $invoice['invoice_number'] }}</p>
        </div>

        <!-- Content -->
        <div class="invoice-content">
            <!-- Status -->
            <div class="invoice-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h5 style="margin: 0;">Invoice Details</h5>
                    <span class="status-badge status-{{ $invoice['status'] }}">{{ ucfirst($invoice['status']) }}</span>
                </div>
                @if($invoice['sent_at'] ?? null)
                <div style="font-size: 12px; color: #28a745; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle"></i>
                    <span>Invoice sent via WhatsApp</span>
                </div>
                @endif
            </div>

            <!-- Customer Info -->
            <div class="invoice-section">
                <div class="section-label">Customer Information</div>
                <div class="invoice-info">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value">{{ $invoice['customer_name'] ?? 'Not provided' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value">{{ $invoice['customer_phone'] }}</div>
                    </div>
                    @if($invoice['customer_email'])
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value">{{ $invoice['customer_email'] }}</div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Items -->
            @if($invoice['items'] && count($invoice['items']) > 0)
            <div class="invoice-section">
                <div class="section-label">Order Items</div>
                <div class="invoice-items">
                    @foreach($invoice['items'] as $item)
                    <div class="item">
                        <div>
                            <div class="item-name">{{ $item['title'] }}</div>
                            @if($item['variant'] ?? null)
                            <div style="font-size: 12px; color: #6c757d;">{{ $item['variant'] }}</div>
                            @endif
                        </div>
                        <div class="item-qty">x{{ $item['quantity'] }}</div>
                        <div class="item-price">KES {{ number_format($item['total'], 2) }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Amount Summary -->
            <div class="invoice-section">
                <div class="amount-summary">
                    @if($invoice['total_paid'] > 0)
                    <div class="amount-row">
                        <span class="amount-label">Amount Due</span>
                        <span>KES {{ number_format($invoice['amount'], 2) }}</span>
                    </div>
                    <div class="amount-row">
                        <span class="amount-label">Amount Paid</span>
                        <span style="color: #28a745;">KES {{ number_format($invoice['total_paid'], 2) }}</span>
                    </div>
                    <div class="amount-row">
                        <span class="amount-label">Remaining</span>
                        <span>KES {{ number_format($invoice['remaining'], 2) }}</span>
                    </div>
                    @else
                    <div class="amount-row">
                        <span class="amount-label">Total Amount</span>
                        <span>KES {{ number_format($invoice['amount'], 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Payment Section -->
            @if($invoice['remaining'] > 0)
            <div class="payment-section">
                <div id="successMessage" class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h5>Payment Initiated!</h5>
                    <p>Please enter your M-Pesa PIN to complete the payment.</p>
                </div>

                <div id="paymentContent">
                    <div class="payment-info">
                        <i class="fas fa-info-circle"></i>
                        <span>You will receive a popup to enter your M-Pesa PIN. The charge will be <strong>KES {{ number_format($invoice['remaining'], 2) }}</strong></span>
                    </div>

                    <button class="pay-button" id="payButton" onclick="initiatePayment()">
                        <i class="fab fa-m"></i> Pay with M-Pesa
                    </button>
                </div>

                <div class="loading" id="loading">
                    <div class="loading-spinner"></div>
                    <p>Initiating payment...</p>
                </div>
            </div>
            @else
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <strong>Payment Complete!</strong> This invoice has been fully paid.
            </div>
            @endif
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const invoiceId = "{{ $invoice['id'] }}";
        const remainingAmount = {{ $invoice['remaining'] }};

        function initiatePayment() {
            const button = document.getElementById('payButton');
            const paymentContent = document.getElementById('paymentContent');
            const loading = document.getElementById('loading');
            const successMessage = document.getElementById('successMessage');

            button.disabled = true;
            paymentContent.style.display = 'none';
            loading.style.display = 'block';

            fetch(`/api/invoice/${invoiceId}/pay`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                },
                body: JSON.stringify({
                    amount: remainingAmount,
                })
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';

                if (data.success) {
                    successMessage.style.display = 'block';
                    
                    // Poll for payment status
                    pollPaymentStatus(data.payment_id);
                } else {
                    alert('Error: ' + (data.message || 'Failed to initiate payment'));
                    paymentContent.style.display = 'block';
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error initiating payment: ' + error.message);
                loading.style.display = 'none';
                paymentContent.style.display = 'block';
                button.disabled = false;
            });
        }

        function pollPaymentStatus(paymentId, attempts = 0) {
            if (attempts > 60) { // Stop after 60 attempts (5 minutes)
                alert('Payment status check timed out. Please check your invoice.');
                location.reload();
                return;
            }

            setTimeout(() => {
                fetch(`/api/invoice/${invoiceId}/payment/${paymentId}/status`, {
                    headers: {
                        'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.payment.status === 'success') {
                            alert('Payment successful! Thank you for your order.');
                            location.reload();
                        } else if (data.payment.status === 'failed') {
                            alert('Payment failed. ' + (data.payment.description || ''));
                            location.reload();
                        } else {
                            // Still pending, poll again
                            pollPaymentStatus(paymentId, attempts + 1);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    pollPaymentStatus(paymentId, attempts + 1);
                });
            }, 5000); // Check every 5 seconds
        }
    </script>
</body>
</html>
