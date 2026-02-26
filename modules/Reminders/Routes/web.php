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
    'middleware' =>['web'],
    'namespace' => 'Modules\Reminders\Http\Controllers'
], function () {
     //PUBLIC API
    Route::prefix('api/reminders/reservation')->group(function() {
        Route::post('makeReservation', 'APIController@createReservation');
    });
});

Route::group([
    'middleware' =>[ 'web','impersonate','XssSanitizer','auth'],
    'namespace' => 'Modules\Reminders\Http\Controllers'
], function () {

    //API
    Route::post('/api/reminders/get-contact-reservations', 'APIController@getContactReservations')->name('reminders.get-contact-reservations');

    Route::prefix('reminders')->group(function() {

        //Reminders
        Route::get('reminders', 'RemindersController@index')->name('reminders.reminders.index');
        Route::get('reminders/{reminder}/edit', 'RemindersController@edit')->name('reminders.reminders.edit');
        Route::get('reminders/create', 'RemindersController@create')->name('reminders.reminders.create');
        Route::post('reminders', 'RemindersController@store')->name('reminders.reminders.store');
        Route::put('reminders/{reminder}', 'RemindersController@update')->name('reminders.reminders.update');
        Route::get('reminders/del/{reminder}', 'RemindersController@destroy')->name('reminders.reminders.delete');
        

         //Source
        Route::get('sources', 'SourcesController@index')->name('reminders.sources.index');
        Route::get('sources/{source}/edit', 'SourcesController@edit')->name('reminders.sources.edit');
        Route::get('sources/create', 'SourcesController@create')->name('reminders.sources.create');
        Route::post('sources', 'SourcesController@store')->name('reminders.sources.store');
        Route::put('sources/{source}', 'SourcesController@update')->name('reminders.sources.update');
        Route::get('sources/del/{source}', 'SourcesController@destroy')->name('reminders.sources.delete');

        //Reservations
        Route::get('reservations', 'ReservationsController@index')->name('reminders.reservations.index');
        Route::get('reservations/{reservation}/edit', 'ReservationsController@edit')->name('reminders.reservations.edit');
        Route::get('reservations/create', 'ReservationsController@create')->name('reminders.reservations.create');
        Route::post('reservations', 'ReservationsController@store')->name('reminders.reservations.store');
        Route::put('reservations/{reservation}', 'ReservationsController@update')->name('reminders.reservations.update');
        Route::get('reservations/del/{reservation}', 'ReservationsController@destroy')->name('reminders.reservations.delete');

       
    });
});



