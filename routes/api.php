<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use Modules\Invoice\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/login', [LoginController::class, 'login']);
Route::post('/v2/login', [LoginController::class, 'login']);

// Invoice payment routes (public - no auth required)
Route::prefix('invoice')->group(function () {
    // Public payment initiation
    Route::post('/{invoice}/pay', [InvoiceController::class, 'initiatePayment'])
        ->name('invoice.pay');

    // Public payment status check
    Route::get('/{invoice}/payment/{payment}/status', [InvoiceController::class, 'checkPaymentStatus'])
        ->name('invoice.payment.status');

    // Payment callback (no middleware)
    Route::post('/payment/callback', [InvoiceController::class, 'handleCallback'])
        ->withoutMiddleware(['api'])
        ->name('invoice.payment.callback');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/create', [InvoiceController::class, 'createFromOrder'])
            ->name('invoice.create');

        Route::get('/list', [InvoiceController::class, 'listInvoices'])
            ->name('invoice.list');

        Route::get('/{invoice}', [InvoiceController::class, 'show'])
            ->name('invoice.show');
    });
});
