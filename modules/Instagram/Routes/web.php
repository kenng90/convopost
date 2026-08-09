<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth', 'verified', 'impersonate', 'XssSanitizer', 'isOwnerOnPro'],
    'namespace' => 'Modules\Instagram\Http\Controllers',
    'prefix' => 'instagram',
], function () {
    Route::middleware('plan.capability:inbox_instagram')->group(function () {
        Route::get('setup', 'SetupController@index')->name('instagram.setup');
        Route::post('setup', 'SetupController@store')->name('instagram.setup.store');
    });
});
