<?php

namespace Modules\Whatsappcatalog\Providers;

use Illuminate\Support\ServiceProvider as Provider;

class Main extends Provider
{
    public function register(): void
    {
        $this->loadRoutes();
    }

    public function boot(): void
    {
        $this->loadViews();
    }

    protected function loadViews(): void
    {
        $sourcePath = __DIR__.'/../Resources/views';

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/whatsappcatalog';
        }, \Config::get('view.paths')), [$sourcePath]), 'whatsappcatalog');
    }

    protected function loadRoutes(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
