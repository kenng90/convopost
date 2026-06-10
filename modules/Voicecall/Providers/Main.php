<?php

namespace Modules\Voicecall\Providers;

use Illuminate\Support\ServiceProvider;

class Main extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'voicecall');
        $this->loadRoutes();
        $this->loadViews();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    protected function loadRoutes(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        foreach (['web.php', 'api.php'] as $route) {
            $path = __DIR__.'/../Routes/'.$route;
            if (is_file($path)) {
                $this->loadRoutesFrom($path);
            }
        }
    }

    protected function loadViews(): void
    {
        $sourcePath = __DIR__.'/../Resources/views';
        $paths = [$sourcePath];

        $publishedPath = resource_path('views/modules/voicecall');
        if (is_dir($publishedPath)) {
            $paths = array_merge(
                array_map(fn ($path) => $path.'/modules/voicecall', config('view.paths')),
                $paths
            );
        }

        $this->loadViewsFrom($paths, 'voicecall');
    }
}
