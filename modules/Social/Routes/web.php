<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\Http\Controllers\AccountController;
use Modules\Social\Http\Controllers\CalendarController;
use Modules\Social\Http\Controllers\FacebookConnectController;
use Modules\Social\Http\Controllers\HomeController;
use Modules\Social\Http\Controllers\InstagramConnectController;
use Modules\Social\Http\Controllers\LinkedInConnectController;
use Modules\Social\Http\Controllers\MediaController;
use Modules\Social\Http\Controllers\OfferRedirectController;
use Modules\Social\Http\Controllers\PostController;

Route::middleware(['web'])
    ->get('/o/{token}', OfferRedirectController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('social.offer.redirect');

Route::middleware(['web', 'impersonate', 'verified', 'auth', 'isOwnerOnPro', 'plan.plugin:social'])
    ->prefix('social')
    ->group(function () {
        Route::get('/', [HomeController::class, '__invoke'])->name('social.home');
        Route::get('/accounts', [AccountController::class, 'index'])->name('social.accounts.index');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('social.accounts.disconnect');

        Route::get('/media', [MediaController::class, 'index'])->name('social.media.index');
        Route::post('/media', [MediaController::class, 'store'])->name('social.media.store');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('social.media.destroy');

        Route::get('/posts', [PostController::class, 'index'])->name('social.posts.index');
        Route::get('/posts/create', [PostController::class, 'create'])->name('social.posts.create');

        Route::get('/calendar', CalendarController::class)->name('social.calendar');

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
