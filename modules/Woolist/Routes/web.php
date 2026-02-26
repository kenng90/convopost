<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([
    'middleware' => ['web', 'impersonate'],
    'namespace' => 'Modules\Woolist\Http\Controllers'
], function () {
    Route::group([
        'middleware' => ['verified', 'web', 'auth', 'impersonate', 'XssSanitizer', 'isOwnerOnPro'],
    ], function () {
        //Force reload
        Route::get('/woolist/getData/{force_reload?}', 'Main@getData');
        Route::post('api/woolist/getOrders', 'Main@getOrders');

    });
});