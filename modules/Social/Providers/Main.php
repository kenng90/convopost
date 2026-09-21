<?php

namespace Modules\Social\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as Provider;

class Main extends Provider
{
    public function register()
    {
        $this->loadConfig();
    }

    public function boot()
    {
        $this->publishConfig();
        $this->loadViews();
        $this->loadViewComponents();
        $this->loadTranslations();
        $this->loadMigrations();
        $this->loadRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Social\Console\RefreshSocialTokensCommand::class,
            ]);
        }
    }

    protected function loadConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'social'
        );
    }

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('social.php'),
        ], 'config');
    }

    public function loadViews()
    {
        $viewPath = resource_path('views/modules/social');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/social';
        }, \Config::get('view.paths')), [$sourcePath]), 'social');
    }

    public function loadViewComponents()
    {
        Blade::componentNamespace('Modules\Social\View\Components', 'social');
    }

    public function loadTranslations()
    {
        $langPath = resource_path('lang/modules/social');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'social');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang/en', 'social');
        }
    }

    public function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    protected function loadRoutes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }

    public function provides()
    {
        return [];
    }
}
