<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::group([
    'middleware' => ['web', 'impersonate'],
    'namespace' => 'Modules\Journies\Http\Controllers',
], function () {
    Route::prefix('api/journies/external')->group(function () {
        Route::post('move-contact', 'ApiController@moveContact')->name('api.journies.external.move-contact');
        Route::get('contact-status', 'ApiController@contactStatus')->name('api.journies.external.contact-status');
    });

    Route::group([
        'middleware' => ['web', 'auth', 'impersonate'],
    ], function () {
        Route::get('api/journies/contact/{contact}', 'Main@getJournies')->name('api.journies.get')->where('contact', '[0-9]+');
        Route::post('api/journies/move-contact', 'StagesController@moveContactFromSideapp')->name('api.journies.move-contact');
        Route::post('journies/{journey}/remove-contact/{contact}', 'Main@removeContact')->name('journies.remove-contact');
    });

    Route::group([
        'middleware' => ['web', 'auth', 'impersonate', 'plan.plugin:journies'],
    ], function () {
        Route::get('journiesindex', 'Main@index')->name('journies.index');
        Route::get('journies/analytics', 'Main@analytics')->name('journies.analytics');
        Route::get('api/journies/analytics', 'Main@analyticsData')->name('api.journies.analytics');
        Route::get('journies/create', 'Main@create')->name('journies.create');
        Route::post('journies', 'Main@store')->name('journies.store');
        Route::get('journies/template/{template}', 'Main@createFromTemplate')->name('journies.create-from-template');
        Route::get('journies/{journey}/edit', 'Main@edit')->name('journies.edit');
        Route::get('journies/{journey}/kanban', 'Main@kanban')->name('journies.kanban');
        Route::put('journies/{journey}', 'Main@update')->name('journies.update');
        Route::delete('journies/{journey}', 'Main@destroy')->name('journies.delete');
        Route::get('journies/del/{journey}', 'Main@destroy')->name('journies.delete.legacy');

        Route::get('api/journies/{journey}/contacts/search', 'Main@searchContacts')->name('api.journies.contacts.search');
        Route::post('journey.add-contact/{journey}', 'Main@addContact')->name('journey.add-contact');

        Route::get('stages/create/{journey}', 'StagesController@create')->name('stages.create');
        Route::post('stages/{journey}', 'StagesController@store')->name('stages.store');
        Route::get('stages/{stage}/edit', 'StagesController@edit')->name('stages.edit');
        Route::put('stages/{stage}', 'StagesController@update')->name('stages.update');
        Route::get('stages/del/{stage}', 'StagesController@destroy')->name('stages.delete');
        Route::post('journies/{journey}/stages/reorder', 'StagesController@reorder')->name('stages.reorder');

        Route::get('stages/{stage}/move-contact/{contact}', 'StagesController@moveContact')->name('stages.move-contact');

        Route::get('journies/{journey}/group-rules', 'StagesController@groupRules')->name('journies.group-rules');
        Route::post('journies/{journey}/group-rules', 'StagesController@storeGroupRule')->name('journies.group-rules.store');
        Route::delete('journies/{journey}/group-rules/{rule}', 'StagesController@destroyGroupRule')->name('journies.group-rules.delete');
    });
});
