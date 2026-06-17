<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoice\Http\Controllers\PublicInvoiceController;

Route::prefix('invoice')->group(function () {
    Route::get('/{invoice:public_uuid}', [PublicInvoiceController::class, 'show'])
        ->name('invoice.public.show');
});
