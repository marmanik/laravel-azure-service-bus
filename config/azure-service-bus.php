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

    // When true, permanently failed jobs are moved to the Azure Service Bus
    // Dead Letter Queue ({queue}/$DeadLetterQueue) instead of being completed
    // (deleted). Messages in the DLQ can be inspected and replayed from the
    // Azure Portal or Service Bus Explorer.
    // When false (default), failed messages are completed and Laravel writes
    // the failure to the failed_jobs database table.
    'use_dead_letter_on_failure' => env('AZURE_SERVICE_BUS_USE_DLQ', false),
];
