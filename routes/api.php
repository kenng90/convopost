<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        'companies' => $companies->map(fn ($c) => [
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

    Route::get('/paystack/callback', [\Modules\Flowmaker\Http\Controllers\PaystackController::class, 'callback'])
        ->name('invoice.paystack.callback');

    Route::post('/paystack/webhook', [\Modules\Flowmaker\Http\Controllers\PaystackController::class, 'webhook'])
        ->withoutMiddleware(['api'])
        ->name('invoice.paystack.webhook');

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

Route::group([
    'middleware' => ['Modules\Wpbox\Http\Middleware\CheckAPIPlan'],
], function () {
    Route::prefix('v1/catalog')->controller(CatalogApiController::class)->group(function () {
        Route::get('me', 'me')->name('catalog.api.me');
        Route::get('catalogs', 'listCatalogs')->name('catalog.api.catalogs');
        Route::get('catalogs/{id}', 'showCatalog')->name('catalog.api.catalog');
        Route::get('catalogs/{id}/items', 'listItems')->name('catalog.api.items');
        Route::post('catalogs/{id}/sync', 'syncCatalog')->name('catalog.api.sync');
        Route::get('collections', 'listCollections')->name('catalog.api.collections');
        Route::get('collections/{slug}', 'showCollection')->name('catalog.api.collection');
        Route::get('experiments', 'listExperiments')->name('catalog.api.experiments');
    });
});
