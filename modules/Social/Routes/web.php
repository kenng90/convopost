<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\HomeController;

Route::middleware(['web', 'impersonate', 'verified', 'auth', 'isOwnerOnPro'])
    ->prefix('social')
    ->group(function () {
        Route::get('/', [HomeController::class, '__invoke'])->name('social.home');
    });
