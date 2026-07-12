<?php

use Illuminate\Http\Request;

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

// MPesa STK Push callback - no auth required (called by Safaricom)
Route::post('/flowmaker/mpesa/callback', [\Modules\Flowmaker\Http\Controllers\MpesaController::class, 'stkCallback'])
    ->name('flowmaker.mpesa.callback');

Route::get('/flowmaker/paystack/callback', [\Modules\Flowmaker\Http\Controllers\PaystackController::class, 'callback'])
    ->name('flowmaker.paystack.callback');

Route::post('/flowmaker/paystack/webhook', [\Modules\Flowmaker\Http\Controllers\PaystackController::class, 'webhook'])
    ->name('flowmaker.paystack.webhook');

Route::middleware('auth:api')->get('/flowmaker', function (Request $request) {
    return $request->user();
});
