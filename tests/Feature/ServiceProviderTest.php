<?php

declare(strict_types=1);

use Marmanik\AzureServiceBus\AzureServiceBusClient;

it('registers the client singleton', function () {
    $client = app(AzureServiceBusClient::class);
    expect($client)->toBeInstanceOf(AzureServiceBusClient::class);

    $client2 = app(AzureServiceBusClient::class);
    expect($client)->toBe($client2);
});

it('merges config', function () {
    expect(config('azure-service-bus.sas_token_ttl'))->toBe(3600);
    expect(config('azure-service-bus.timeout'))->toBe(30);
});

it('publishes config', function () {
    $this->artisan('vendor:publish', ['--tag' => 'azure-service-bus-config', '--force' => true])
        ->assertExitCode(0);
});
