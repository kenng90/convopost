<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'impersonate'],
    'namespace' => 'Modules\Whatsappcatalog\Http\Controllers',
], function () {
    Route::group([
        'middleware' => ['verified', 'web', 'auth', 'impersonate', 'XssSanitizer', 'isOwnerOnPro', 'plan.plugin:whatsappcatalog'],
    ], function () {
        Route::get('/whatsappcatalog/sidebar/catalogs', 'SidebarController@catalogs');
        Route::post('/api/whatsappcatalog/search-products', 'SidebarController@searchProducts');
    });
});
