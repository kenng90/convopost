<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\AccountController;
use Modules\Social\Http\Controllers\FacebookConnectController;
use Modules\Social\Http\Controllers\HomeController;
use Modules\Social\Http\Controllers\InstagramConnectController;
use Modules\Social\Http\Controllers\LinkedInConnectController;
use Modules\Social\Http\Controllers\MediaController;

Route::middleware(['web', 'impersonate', 'verified', 'auth', 'isOwnerOnPro', 'plan.plugin:social'])
    ->prefix('social')
    ->group(function () {
        Route::get('/', [HomeController::class, '__invoke'])->name('social.home');
        Route::get('/accounts', [AccountController::class, 'index'])->name('social.accounts.index');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('social.accounts.disconnect');

        Route::post('/media', [MediaController::class, 'store'])->name('social.media.store');

        Route::get('/accounts/connect/facebook', [FacebookConnectController::class, 'redirect'])
            ->name('social.accounts.connect.facebook');
        Route::get('/accounts/connect/facebook/callback', [FacebookConnectController::class, 'callback'])
            ->name('social.accounts.connect.facebook.callback');

        Route::get('/accounts/connect/instagram', [InstagramConnectController::class, 'redirect'])
            ->name('social.accounts.connect.instagram');
        Route::get('/accounts/connect/instagram/callback', [InstagramConnectController::class, 'callback'])
            ->name('social.accounts.connect.instagram.callback');

        Route::get('/accounts/connect/linkedin', [LinkedInConnectController::class, 'redirect'])
            ->name('social.accounts.connect.linkedin');
        Route::get('/accounts/connect/linkedin/callback', [LinkedInConnectController::class, 'callback'])
            ->name('social.accounts.connect.linkedin.callback');
    });
