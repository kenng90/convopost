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

Route::middleware('auth:api')->get('/flowmaker', function (Request $request) {
    return $request->user();
});

