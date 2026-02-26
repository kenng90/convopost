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
        'namespace' => 'Modules\Blog\Http\Controllers'
    ], function () {
        Route::group([
            'middleware' =>['verified', 'web','auth','impersonate','isOwnerOnPro'],
        ], function () {
            //Blog Management
            Route::resource('blogging', 'Main')->parameters(['blogging' => 'item']);
            Route::get('blogging/{item}/delete', 'Main@destroy')->name('blogging.delete');
            Route::get('blogging/{item}/clone', 'Main@clone')->name('blogging.clone');
        });
    });

    Route::group([
        'middleware' =>[ 'web','impersonate'],
        'namespace' => 'Modules\Blog\Http\Controllers'
    ], function () {
        //API
        Route::get('/api/blog', 'Main@all')->name('blog.all');
        Route::get('/api/blog/{slug}', 'Main@single')->name('blog.single');
    });



