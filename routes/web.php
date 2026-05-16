<?php

use App\Http\Controllers\Auth\MyWelcomeController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\CompaniesController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\CRUD\PostsController;
use App\Http\Controllers\FlowsController;
use App\Http\Controllers\FrontEndController;
use App\Http\Controllers\ListCatalogController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\WelcomeNotification\WelcomesNewUsers;
use App\Http\Controllers\FlowBuilderController;

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

Route::get('/', [FrontEndController::class, 'index'])->name('landing');
Route::get('/new', [FrontEndController::class, 'register'])->name('newcompany.register');
Route::get('/'.config('settings.url_route', 'company').'/{alias}', [FrontEndController::class, 'company'])->name('vendor');
Route::get('/notify/{type}/{id}/{message}', [CompaniesController::class, 'notify'])->name('company.notify');

// Public Catalog Routes (no authentication required)
Route::controller(PublicCatalogController::class)->prefix('catalog')->group(function () {
    // Specific routes first (more specific before catch-all)
    Route::get('/invoice/{invoiceId}', 'getInvoice')->name('catalog.invoice');
    Route::get('/pay/{invoiceId}', 'showInvoice')->name('catalog.invoice.pay');

    // Then parameterized routes
    Route::get('/{catalogId}/items', 'getItems')->name('catalog.items');
    Route::post('/{catalogId}/generate-order', 'generateOrder')->name('catalog.generate-order');
    Route::post('/{catalogId}/create-invoice', 'createInvoice')->name('catalog.create-invoice');

    // Finally catch-all (most generic)
    Route::get('/{catalogId}', 'show')->name('catalog.public');
});
Route::middleware('web', WelcomesNewUsers::class)->group(function () {
    Route::get('welcome/{user}', [MyWelcomeController::class, 'showWelcomeForm'])->name('welcome');
    Route::post('welcome/{user}', [MyWelcomeController::class, 'savePassword']);
});

//AUTH
Route::get('/session-test', function () {
    session(['ping' => 'pong']);
    return session('ping');
});

Route::middleware('web')->group(function () {
    Route::get('/login/google', [SocialController::class, 'googleRedirectToProvider'])->name('google.login');
    Route::get('/login/google/redirect', [SocialController::class, 'googleHandleProviderCallback']);
    Route::get('/login/facebook', [App\Http\Controllers\Auth\SocialController::class, 'facebookRedirectToProvider'])->name('facebook.login');
    Route::get('/login/facebook/redirect', [SocialController::class, 'facebookHandleProviderCallback']);

    //password/reset to /forgot-password
    Route::get('password/reset', function () {
        return redirect('forgot-password');
    });

});

Route::middleware(['web', 'auth', 'impersonate', 'acivatedProject'])->group(function () {
    Route::get('/dashboard/{lang?}', [App\Http\Controllers\DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/home/{lang?}', [App\Http\Controllers\DashboardController::class, 'dashboard'])->name('home');

    Route::name('admin.')->group(function () {
        Route::resource(config('settings.url_route_plural', 'companies'), 'App\Http\Controllers\CompaniesController', [
            'names' => [
                'index' => 'companies.index',
                'store' => 'companies.store',
                'edit' => 'companies.edit',
                'create' => 'companies.create',
                'destroy' => 'companies.destroy',
                'update' => 'companies.update',
                'show' => 'companies.show',
            ],
        ]);

        //Other companies routes
        Route::get('removecompany/{company}', [App\Http\Controllers\CompaniesController::class, 'remove'])->name('company.remove');
        Route::get('/company/{company}/activate', [App\Http\Controllers\CompaniesController::class, 'activateCompany'])->name('company.activate');
        Route::put('companies_app_update/{company}', [App\Http\Controllers\CompaniesController::class, 'updateApps'])->name('company.updateApps');
        Route::get('companies/loginas/{company}', [App\Http\Controllers\CompaniesController::class, 'loginas'])->name('companies.loginas');
        Route::get('logout_companies/logout-create', [App\Http\Controllers\CompaniesController::class, 'logoutAndRedirectToRegister'])->name('companies.logout-create');

        //Switch company
        Route::get('companies/switch/{company}', [App\Http\Controllers\CompaniesController::class, 'switch'])->name('companies.switch');

        //Organization management
        Route::get('organizations/manage', [App\Http\Controllers\CompaniesController::class, 'manage'])->name('organizations.manage');
        Route::post('organizations/create', [App\Http\Controllers\CompaniesController::class, 'createOrganization'])->name('organizations.create');

        Route::get('stopimpersonate', [App\Http\Controllers\CompaniesController::class, 'stopImpersonate'])->name('companies.stopImpersonate');
        Route::get('/share', [App\Http\Controllers\CompaniesController::class, 'share'])->name('share');

        Route::resource('settings', 'App\Http\Controllers\SettingsController');

        // Backup
        Route::get('backup', [App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
        Route::get('backup/download-languages', [App\Http\Controllers\BackupController::class, 'downloadLanguageBackup'])->name('backup.download.languages');
        Route::post('backup/restore-languages', [App\Http\Controllers\BackupController::class, 'restoreLanguages'])->name('backup.restore.languages');

        // Landing page settings
        Route::get('landing', [App\Http\Controllers\SettingsController::class, 'landing'])->name('landing');
        Route::controller(PostsController::class)->prefix('landing')->name('landing.')->group(function () {
            Route::get('posts/{type}', 'index')->name('posts');
            Route::get('posts/{type}/create', 'create')->name('posts.create');
            Route::post('posts/{type}', 'store')->name('posts.store');

            Route::get('posts/edit/{post}', 'edit')->name('posts.edit');
            Route::put('posts/{post}', 'update')->name('posts.update');
            Route::get('posts/del/{post}', 'destroy')->name('posts.delete');

        });

        //Apps
        Route::get('apps', [App\Http\Controllers\AppsController::class, 'index'])->name('apps.index');
        Route::get('company_apps', [App\Http\Controllers\AppsController::class, 'companyApps'])->name('apps.company');
        Route::get('appremove/{alias}', [App\Http\Controllers\AppsController::class, 'remove'])->name('apps.remove');
        Route::post('apps', [App\Http\Controllers\AppsController::class, 'store'])->name('apps.store');
        Route::put('company_apps_update', [App\Http\Controllers\AppsController::class, 'updateApps'])->name('owner.updateApps');
        Route::get('apps/update_plugin_via_file', [App\Http\Controllers\AppsController::class, 'store'])->name('apps.update_plugin_via_file');
    });

    Route::resource('plans', PlansController::class);
    Route::controller(PlansController::class)->group(function () {
        Route::get('/plan', 'current')->name('plans.current');
        Route::post('/subscribe/plan', 'subscribe')->name('plans.subscribe');
        Route::get('/subscribe/cancel', 'cancelStripeSubscription')->name('plans.cancel');
        Route::get('/subscribe/plan3d/{plan}/{user}', 'subscribe3dStripe')->name('plans.subscribe_3d_stripe');
        Route::post('/subscribe/update', 'adminupdate')->name('update.plan');
    });

    Route::resource('credits', CreditsController::class);
    Route::post('/credits/costs', [CreditsController::class, 'updateCosts'])->name('credits.costs');
    Route::get('/billing', function (Request $request) {
        return $request->user()->redirectToBillingPortal(route('plans.current'));
    })->name('billing');

    Route::middleware('plan.plugin:whatsappcatalog')->group(function () {
        Route::get('/catalogs', [SettingsController::class, 'catalogs'])->name('catalogs.page');

        Route::controller(ListCatalogController::class)->group(function () {
            Route::post('/api/list-catalogs/preview-excel', 'previewExcel')->name('catalogs.preview-excel');
            Route::post('/api/list-catalogs/import-excel', 'importExcel')->name('catalogs.import-excel');
            Route::post('/api/list-catalogs/test-api', 'testAPI')->name('catalogs.test-api');
            Route::get('/api/list-catalogs', 'listCatalogs')->name('catalogs.list');
            Route::get('/api/list-catalogs/{id}', 'getCatalog')->name('catalogs.show');
            Route::put('/api/list-catalogs/{id}', 'updateCatalog')->name('catalogs.update');
            Route::delete('/api/list-catalogs/{id}', 'deleteCatalog')->name('catalogs.delete');

            Route::get('/api/list-catalogs/{id}/manage/items', 'getItems')->name('catalogs.items');
            Route::post('/api/list-catalogs/{id}/manage/items', 'addItem')->name('catalogs.items.add');
            Route::put('/api/list-catalogs/{id}/manage/items/{itemId}', 'updateItem')->name('catalogs.items.update');
            Route::delete('/api/list-catalogs/{id}/manage/items/{itemId}', 'deleteItem')->name('catalogs.items.delete');
        });
    });

    Route::middleware('plan.plugin:whatsappflows')->group(function () {
        Route::get('/api/whatsapp-flows', [FlowsController::class, 'listForBuilder'])->name('whatsapp-flows.list-builder');

        Route::prefix('api/flow-builder')->name('flow-builder.')->group(function () {
            Route::get('/{flow}', [FlowBuilderController::class, 'load'])->name('load');
            Route::post('/', [FlowBuilderController::class, 'store'])->name('store');
            Route::put('/{flow}', [FlowBuilderController::class, 'update'])->name('update');
            Route::post('/{flow}/publish', [FlowBuilderController::class, 'publish'])->name('publish');
            Route::post('/{flow}/republish', [FlowBuilderController::class, 'republish'])->name('republish');
            Route::get('/endpoint-url', [App\Http\Controllers\Api\FlowBuilderController::class, 'endpointUrl'])->name('endpoint-url');
        });

        Route::controller(FlowsController::class)->prefix('whatsapp-flows')->name('whatsapp-flows.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'index')->name('create');
            Route::get('/{id}/edit', 'index')->name('edit');
            Route::get('/responses/dashboard', function () {
                return view('whatsapp-flows-responses');
            })->name('responses');

            Route::get('/api/list', 'list')->name('list');
            Route::get('/api/{id}', 'getFlow')->name('show');
            Route::post('/api', 'create')->name('store');
            Route::put('/api/{id}', 'update')->name('update');
            Route::delete('/api/{id}', 'delete')->name('delete');
            Route::post('/api/{id}/publish', 'publish')->name('publish');
            Route::post('/api/{id}/archive', 'archive')->name('archive');
            Route::post('/api/{id}/publish-to-meta', 'publishToMeta')->name('publish-to-meta');
        });
    });

    // Reports Routes
    Route::controller(ReportsController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/transactions', 'transactions')->name('transactions');
        Route::get('/payments', 'payments')->name('payments');
        Route::get('/reconciliation', 'reconciliation')->name('reconciliation');
        Route::get('/daily-summary', 'dailySummary')->name('daily-summary');
    });

});

//Verify
Route::middleware('web')->group(function () {
    Route::get('/activation/{code}', [SettingsController::class, 'activation'])->name('project.activation');
});

//Static pages or vendor by alias
Route::middleware('web')->group(function () {
    Route::get('/{alias}', [FrontEndController::class, 'staticPage'])->name('static-page')
        ->where('alias', '^(?!flows|whatsapp-flows|dashboard|home|reports|reports/|api/|login|logout|password|register|forgot-password).*');
});