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

Route::middleware('auth:sanctum')->get('/accessible-companies', function (Request $request) {
    $user = $request->user();
    $companies = $user->accessibleCompanies();

    return response()->json([
        'success' => true,
        'companies' => $companies->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
        ])->toArray(),
    ]);
});


// Route::post('/login', [LoginController::class, 'login']);
Route::post('/v2/login', [LoginController::class, 'login']);

// Invoice payment routes (public - no auth required, but validates ownership via UUID)
Route::prefix('invoice')->group(function () {
    // Payment callback (no middleware) - signature validation inside controller
    Route::post('/payment/callback', [InvoiceController::class, 'handleCallback'])
        ->withoutMiddleware(['api'])
        ->name('invoice.payment.callback');

    // Public payment routes (with rate limiting)
    Route::middleware('throttle:10,1')->group(function () {
        // Public payment initiation (uses UUID for route binding)
        Route::post('/{invoice:public_uuid}/pay', [InvoiceController::class, 'initiatePayment'])
            ->name('invoice.pay');

        // Public payment status check (uses UUID for route binding)
        Route::get('/{invoice:public_uuid}/payment/{payment}/status', [InvoiceController::class, 'checkPaymentStatus'])
            ->name('invoice.payment.status');
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/create', [InvoiceController::class, 'createFromOrder'])
            ->name('invoice.create');

        Route::get('/list', [InvoiceController::class, 'listInvoices'])
            ->name('invoice.list');

        Route::get('/{invoice:id}', [InvoiceController::class, 'show'])
            ->name('invoice.show');
    });
});
