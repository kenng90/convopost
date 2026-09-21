<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\AccountController;
use Modules\Social\Http\Controllers\FacebookConnectController;
use Modules\Social\Http\Controllers\HomeController;
use Modules\Social\Http\Controllers\InstagramConnectController;

Route::middleware(['web', 'impersonate', 'verified', 'auth', 'isOwnerOnPro', 'plan.plugin:social'])
    ->prefix('social')
    ->group(function () {
        Route::get('/', [HomeController::class, '__invoke'])->name('social.home');
        Route::get('/accounts', [AccountController::class, 'index'])->name('social.accounts.index');

        Route::get('/accounts/connect/facebook', [FacebookConnectController::class, 'redirect'])
            ->name('social.accounts.connect.facebook');
        Route::get('/accounts/connect/facebook/callback', [FacebookConnectController::class, 'callback'])
            ->name('social.accounts.connect.facebook.callback');

        Route::get('/accounts/connect/instagram', [InstagramConnectController::class, 'redirect'])
            ->name('social.accounts.connect.instagram');
        Route::get('/accounts/connect/instagram/callback', [InstagramConnectController::class, 'callback'])
            ->name('social.accounts.connect.instagram.callback');
    });
