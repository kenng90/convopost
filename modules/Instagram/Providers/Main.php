<?php

namespace Modules\Instagram\Providers;

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
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'instagram');
    }

    protected function loadRoutes(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
