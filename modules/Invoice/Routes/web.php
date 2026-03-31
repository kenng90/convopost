<?php

use Illuminate\Support\Facades\Route;

Route::prefix('invoice')->group(function () {
    // Public invoice view (guest can view invoice)
    Route::get('/{invoiceNumber}/view', function ($invoiceNumber) {
        // This will be implemented when adding public invoice view
        return response()->json([
            'message' => 'Public invoice view not yet implemented',
        ]);
    })->name('invoice.public.view');
});
