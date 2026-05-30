<?php

namespace Modules\WhatsappcallWorker\Providers;

use Illuminate\Support\ServiceProvider;

class Main extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'whatsappcallworker');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\WhatsappcallWorker\Console\StartWorkerCommand::class,
            ]);
        }
    }
}
