<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'impersonate'],
    'namespace' => 'Modules\Platform\Http\Controllers',
], function () {
    Route::group([
        'middleware' => ['verified', 'web', 'auth', 'impersonate', 'XssSanitizer'],
    ], function () {
        Route::get('activation', 'ActivationController@index')->name('activation.index');
        Route::post('activation/complete', 'ActivationController@complete')->name('activation.complete');
        Route::post('activation/skip', 'ActivationController@skip')->name('activation.skip');
        Route::post('activation/test-message', 'ActivationController@markTestMessage')->name('activation.test-message');

        Route::get('api/health-alerts', 'HealthMonitorController@index')->name('health-alerts.index');
        Route::get('api/customer360/{contact}', 'Customer360Controller@show')->name('customer360.show');

        Route::middleware('isOwnerOnPro')->group(function () {
            Route::get('integrations', 'IntegrationHubController@index')->name('integrations.index');
            Route::post('integrations/{provider}/connect', 'IntegrationHubController@connect')->name('integrations.connect');
            Route::delete('integrations/{provider}', 'IntegrationHubController@disconnect')->name('integrations.disconnect');
        });
    });
});
