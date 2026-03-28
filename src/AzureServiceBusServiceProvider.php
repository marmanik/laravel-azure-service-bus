<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Marmanik\AzureServiceBus\Queue\AzureServiceBusConnector;

class AzureServiceBusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/azure-service-bus.php',
            'azure-service-bus',
        );

        $this->app->singleton(AzureServiceBusClient::class, fn () => AzureServiceBusClient::fromConfig());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/azure-service-bus.php' => config_path('azure-service-bus.php'),
            ], 'azure-service-bus-config');
        }

        Queue::addConnector('azure-service-bus', fn () => new AzureServiceBusConnector());
    }
}
