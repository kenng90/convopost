<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth', 'verified', 'impersonate', 'XssSanitizer', 'isOwnerOnPro'],
    'namespace' => 'Modules\Messenger\Http\Controllers',
    'prefix' => 'messenger',
], function () {
    Route::get('setup', 'SetupController@index')->name('messenger.setup');
    Route::middleware('plan.capability:inbox_messenger')->group(function () {
        Route::post('setup', 'SetupController@store')->name('messenger.setup.store');
    });
});
