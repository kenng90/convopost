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
    'middleware' =>[ 'web','impersonate'],
    'namespace' => 'Modules\Journies\Http\Controllers'
], function () {
    Route::group([
        'middleware' =>[ 'web','auth','impersonate']
    ], function () {
        //Journies
        Route::get('journiesindex', 'Main@index')->name('journies.index');
        Route::get('journies/{journey}/edit', 'Main@edit')->name('journies.edit');
        Route::get('journies/{journey}/kanban', 'Main@kanban')->name('journies.kanban');
        Route::get('journies/create', 'Main@create')->name('journies.create');
        Route::post('journies', 'Main@store')->name('journies.store');
        Route::put('journies/{journey}', 'Main@update')->name('journies.update');
        Route::get('journies/del/{journey}', 'Main@destroy')->name('journies.delete');

        //Api
        Route::get('api/journies/{contact}', 'Main@getJournies')->name('api.journies.get');
        Route::post('journey.add-contact/{journey}', 'Main@addContact')->name('journey.add-contact');

       //Stages
       Route::get('stages', 'StagesController@index')->name('stages.index');
       Route::get('stages/create/{journey}', 'StagesController@create')->name('stages.create');
       Route::post('stages/{journey}', 'StagesController@store')->name('stages.store');
       Route::get('stages/{stage}/edit', 'StagesController@edit')->name('stages.edit');
       Route::put('stages/{stage}', 'StagesController@update')->name('stages.update');
       Route::get('stages/del/{stage}', 'StagesController@destroy')->name('stages.delete');

       Route::get('stages/{stage}/move-contact/{contact}', 'StagesController@moveContact')->name('stages.move-contact');

       //Api
       Route::post('api/journies/move-contact', 'StagesController@moveContactFromSideapp')->name('api.journies.move-contact');
    });
});
