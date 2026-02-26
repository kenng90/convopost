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

Route::middleware(['web', 'auth'])->namespace('Modules\Smswpbox\Http\Controllers')->group(function() {
    Route::post('api/smswpbox/send', 'Main@send');
    Route::get('api/smswpbox/templates', 'Main@getTemplates');
});
