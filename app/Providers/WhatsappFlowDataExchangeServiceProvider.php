<?php

namespace App\Providers;

use App\Services\WhatsappFlows\ConfigurableDataExchangeHandler;
use App\Services\WhatsappFlows\DynamicOptionsDataExchangeHandler;
use App\Services\WhatsappFlows\WhatsappFlowDataExchangeRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Reminders\Services\BookingFlowDataExchangeHandler;

class WhatsappFlowDataExchangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsappFlowDataExchangeRegistry::class, fn () => new WhatsappFlowDataExchangeRegistry);
    }

    public function boot(): void
    {
        $registry = $this->app->make(WhatsappFlowDataExchangeRegistry::class);

        $registry->register($this->app->make(DynamicOptionsDataExchangeHandler::class));
        $registry->register($this->app->make(BookingFlowDataExchangeHandler::class));
        $registry->register($this->app->make(ConfigurableDataExchangeHandler::class));
    }
}
