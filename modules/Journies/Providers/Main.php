<?php

namespace Modules\Journies\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as Provider;
use Modules\Contacts\Models\Contact;
use Modules\Journies\Events\ContactAddedToGroup;
use Modules\Journies\Events\ContactMovedToStage;
use Modules\Journies\Listeners\ApplyJourneyGroupRules;
use Modules\Journies\Listeners\DispatchJourneyCampaign;
use Modules\Journies\Listeners\EnrollContactOnCreate;
use Modules\Journies\Services\JourneyContactService;

class Main extends Provider
{
    public function register()
    {
        $this->loadConfig();
        $this->loadRoutes();

        $this->app->singleton(JourneyContactService::class);

        $this->app['events']->listen(
            ContactMovedToStage::class,
            DispatchJourneyCampaign::class
        );

        $this->app['events']->listen(
            ContactAddedToGroup::class,
            function (ContactAddedToGroup $event) {
                app(ApplyJourneyGroupRules::class)->handle($event->contact, $event->groupId);
            }
        );
    }

    public function boot()
    {
        $this->publishConfig();
        $this->loadViews();
        $this->loadViewComponents();
        $this->loadTranslations();
        $this->loadMigrations();

        Contact::created(function (Contact $contact) {
            app(EnrollContactOnCreate::class)->handle($contact);
        });
    }

    protected function loadConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'journies'
        );
    }

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('journies.php'),
        ], 'config');
    }

    public function loadViews()
    {
        $viewPath = resource_path('views/modules/journies');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/journies';
        }, \Config::get('view.paths')), [$sourcePath]), 'journies');
    }

    public function loadViewComponents()
    {
        Blade::componentNamespace('Modules\Journies\View\Components', 'journies');
    }

    public function loadTranslations()
    {
        $langPath = resource_path('lang/modules/journies');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'journies');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang/en', 'journies');
        }
    }

    public function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    public function loadRoutes()
    {
        if (app()->routesAreCached()) {
            return;
        }

        $routes = [
            'web.php',
            'api.php',
        ];

        foreach ($routes as $route) {
            $this->loadRoutesFrom(__DIR__.'/../Routes/'.$route);
        }
    }

    public function provides()
    {
        return [];
    }
}
