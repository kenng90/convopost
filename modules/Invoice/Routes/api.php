<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoice\Http\Controllers\InvoiceController;

Route::prefix('invoice')->middleware('api')->group(function () {
    // Public endpoints (no auth required)
    Route::post('/payment/callback', [InvoiceController::class, 'handleCallback'])
        ->withoutMiddleware(['api'])
        ->name('invoice.payment.callback');

    // Public payment initiation (for public invoice payment pages)
    Route::post('/{invoice}/pay', [InvoiceController::class, 'initiatePayment'])
        ->name('invoice.pay');

    // Public payment status check
    Route::get('/{invoice}/payment/{payment}/status', [InvoiceController::class, 'checkPaymentStatus'])
        ->name('invoice.payment.status');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Create invoice from order
        Route::post('/create', [InvoiceController::class, 'createFromOrder'])
            ->name('invoice.create');

        // List invoices
        Route::get('/list', [InvoiceController::class, 'listInvoices'])
            ->name('invoice.list');

        // Get invoice details
        Route::get('/{invoice}', [InvoiceController::class, 'show'])
            ->name('invoice.show');
    });
});
