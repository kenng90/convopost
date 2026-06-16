<?php

Route::group([
    'middleware' => ['web', 'auth', 'impersonate', 'org.route'],
    'namespace' => 'Modules\Managers\Http\Controllers',
], function () {
    Route::prefix('org-managers')->group(function () {
        Route::get('/list', 'Main@index')->name('orgmanager.index');
        Route::get('/create', 'Main@create')->name('orgmanager.create');
        Route::post('/', 'Main@store')->name('orgmanager.store');
        Route::get('/{membership}/edit', 'Main@edit')->name('orgmanager.edit');
        Route::put('/{membership}', 'Main@update')->name('orgmanager.update');
        Route::get('/del/{membership}', 'Main@destroy')->name('orgmanager.delete');
        Route::get('/loginas/{membership}', 'Main@loginas')->name('orgmanager.loginas');

        Route::get('/templates/list', 'Main@templatesIndex')->name('orgmanager.templates.index');
        Route::post('/templates', 'Main@templatesStore')->name('orgmanager.templates.store');
        Route::get('/templates/del/{template}', 'Main@templatesDestroy')->name('orgmanager.templates.delete');
    });
});
