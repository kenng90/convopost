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
        Route::post('activation/vertical', 'ActivationController@installVertical')->name('activation.vertical');

        Route::get('api/health-alerts', 'HealthMonitorController@index')->name('health-alerts.index');
        Route::get('api/customer360/{contact}', 'Customer360Controller@show')->name('customer360.show');
        Route::get('api/managed-ai/status', 'DashboardController@managedAiStatus')->name('managed-ai.status');

        Route::get('outcomes', 'OutcomesController@index')->name('outcomes.index');
        Route::get('trust', 'TrustController@index')->name('trust.index');
        Route::get('agency', 'AgencyController@index')->name('agency.index');

        Route::middleware('isOwnerOnPro')->group(function () {
            Route::get('integrations', 'IntegrationHubController@index')->name('integrations.index');
            Route::post('integrations/{provider}/connect', 'IntegrationHubController@connect')->name('integrations.connect');
            Route::delete('integrations/{provider}', 'IntegrationHubController@disconnect')->name('integrations.disconnect');
            Route::post('integrations/{provider}/test', 'IntegrationHubController@testEvent')->name('integrations.test');

            Route::post('outcomes/suite', 'OutcomesController@installSuite')->name('outcomes.install-suite');
            Route::post('outcomes/{playbook}', 'OutcomesController@install')->name('outcomes.install');
            Route::post('agency/clone-playbook', 'AgencyController@clonePlaybook')->name('agency.clone-playbook');
        });
    });
});
