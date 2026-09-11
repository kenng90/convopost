<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth', 'verified', 'impersonate', 'XssSanitizer', 'isOwnerOnPro'],
    'namespace' => 'Modules\Tiktok\Http\Controllers',
    'prefix' => 'tiktok',
], function () {
    Route::get('setup', 'SetupController@index')->name('tiktok.setup');
    Route::middleware('plan.capability:inbox_tiktok')->group(function () {
        Route::post('setup', 'SetupController@store')->name('tiktok.setup.store');
        Route::get('oauth/redirect', 'SetupController@redirect')->name('tiktok.oauth.redirect');
        Route::get('callback', 'SetupController@callback')->name('tiktok.oauth.callback');
    });
});
