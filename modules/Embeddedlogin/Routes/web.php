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

Route::prefix('embeddedlogin')->group(function() {
    Route::get('/', 'EmbeddedloginController@index');
});


Route::group([
    'middleware' =>[ 'web','impersonate'],
    'namespace' => 'Modules\Embeddedlogin\Http\Controllers'
], function () {
    Route::group([
        'middleware' =>['auth'],
    ], function () {
        //Chat
        Route::get('/embeddedlogin/api/{code}', 'Main@start')->name('embeddedlogin.api');

    });
});