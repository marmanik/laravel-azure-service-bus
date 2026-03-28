<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Tests;

use Marmanik\AzureServiceBus\AzureServiceBusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AzureServiceBusServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('azure-service-bus.namespace', 'test-namespace');
        config()->set('azure-service-bus.sas_key_name', 'TestKeyName');
        config()->set('azure-service-bus.sas_key', 'dGVzdC1rZXktdmFsdWU=');
    }
}
