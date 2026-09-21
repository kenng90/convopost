<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\AccountController;
use Modules\Social\Http\Controllers\HomeController;

Route::middleware(['web', 'impersonate', 'verified', 'auth', 'isOwnerOnPro', 'plan.plugin:social'])
    ->prefix('social')
    ->group(function () {
        Route::get('/', [HomeController::class, '__invoke'])->name('social.home');
        Route::get('/accounts', [AccountController::class, 'index'])->name('social.accounts.index');
    });
