<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Queue;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Marmanik\AzureServiceBus\AzureServiceBusClient;
use Marmanik\AzureServiceBus\Exceptions\AzureServiceBusException;

class AzureServiceBusConnector implements ConnectorInterface
{
    public function connect(array $config): AzureServiceBusQueue
    {
        $connectionString = $config['connection_string'] ?? null;

        if ($connectionString) {
            $client = AzureServiceBusClient::fromConnectionString($connectionString);
        } elseif (isset($config['namespace'], $config['sas_key_name'], $config['sas_key'])) {
            $client = new AzureServiceBusClient(
                namespace: $config['namespace'],
                sasKeyName: $config['sas_key_name'],
                sasKey: $config['sas_key'],
                tokenTtl: (int) ($config['sas_token_ttl'] ?? 3600),
                timeout: (int) ($config['timeout'] ?? 30),
                retries: (int) ($config['retries'] ?? 3),
                retryDelayMs: (int) ($config['retry_delay_ms'] ?? 1000),
            );
        } else {
            $client = AzureServiceBusClient::fromConfig();
        }

        $defaultQueue = $config['queue'] ?? config('azure-service-bus.default_queue', 'default');

        return new AzureServiceBusQueue($client, $defaultQueue);
    }
}
