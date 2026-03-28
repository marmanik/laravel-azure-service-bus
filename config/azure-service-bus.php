<?php

return [
    // Supports connection string OR individual params
    'connection_string' => env('AZURE_SERVICE_BUS_CONNECTION_STRING'),

    // Individual connection params (used if connection_string is null)
    'namespace'    => env('AZURE_SERVICE_BUS_NAMESPACE'),
    'sas_key_name' => env('AZURE_SERVICE_BUS_SAS_KEY_NAME', 'RootManageSharedAccessKey'),
    'sas_key'      => env('AZURE_SERVICE_BUS_SAS_KEY'),

    'default_queue'  => env('AZURE_SERVICE_BUS_QUEUE', 'default'),
    'sas_token_ttl'  => env('AZURE_SERVICE_BUS_SAS_TTL', 3600),
    'timeout'        => env('AZURE_SERVICE_BUS_TIMEOUT', 30),
    'retries'        => env('AZURE_SERVICE_BUS_RETRIES', 3),
    'retry_delay_ms' => env('AZURE_SERVICE_BUS_RETRY_DELAY', 1000),
];
