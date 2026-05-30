<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsappcall\Http\Controllers\CallWorkerController;
use Modules\Whatsappcall\Http\Middleware\ValidateAiWorkerSecret;

Route::prefix('api/whatsappcall/worker')
    ->middleware(ValidateAiWorkerSecret::class)
    ->group(function () {
        Route::get('calls/{call}', [CallWorkerController::class, 'show'])
            ->whereNumber('call');
        Route::post('calls/{call}/pre-accept', [CallWorkerController::class, 'preAccept'])
            ->whereNumber('call');
        Route::post('calls/{call}/accept', [CallWorkerController::class, 'accept'])
            ->whereNumber('call');
        Route::post('calls/{call}/terminate', [CallWorkerController::class, 'terminate'])
            ->whereNumber('call');
        Route::post('calls/{call}/complete', [CallWorkerController::class, 'complete'])
            ->whereNumber('call');
    });
