# Laravel Azure Service Bus

[![run-tests](https://github.com/marmanik/laravel-azure-service-bus/actions/workflows/run-tests.yml/badge.svg)](https://github.com/marmanik/laravel-azure-service-bus/actions/workflows/run-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/MarmaNik/laravel-azure-service-bus.svg)](https://packagist.org/packages/MarmaNik/laravel-azure-service-bus)
[![License](https://img.shields.io/packagist/l/MarmaNik/laravel-azure-service-bus.svg)](LICENSE.md)

A Laravel package to publish and consume messages from Azure Service Bus queues and topics via the REST API.

## Installation

Install the package via Composer:

```bash
composer require marmanik/laravel-azure-service-bus
```

The package auto-discovers and registers itself via Laravel's package discovery.

Publish the config file:

```bash
php artisan vendor:publish --tag=azure-service-bus-config
```

## Configuration

Add the following variables to your `.env` file.

**Option A: Connection String**

```env
AZURE_SERVICE_BUS_CONNECTION_STRING="Endpoint=sb://your-namespace.servicebus.windows.net/;SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=your-key="
```

**Option B: Individual Parameters**

```env
AZURE_SERVICE_BUS_NAMESPACE=your-namespace
AZURE_SERVICE_BUS_SAS_KEY_NAME=RootManageSharedAccessKey
AZURE_SERVICE_BUS_SAS_KEY=your-sas-key
```

**Optional settings:**

```env
AZURE_SERVICE_BUS_QUEUE=default
AZURE_SERVICE_BUS_SAS_TTL=3600
AZURE_SERVICE_BUS_TIMEOUT=30
AZURE_SERVICE_BUS_RETRIES=3
AZURE_SERVICE_BUS_RETRY_DELAY=1000

# Dead Letter Queue — move failed jobs to DLQ instead of completing them
AZURE_SERVICE_BUS_USE_DLQ=false
```

The full `config/azure-service-bus.php`:

```php
return [
    'connection_string' => env('AZURE_SERVICE_BUS_CONNECTION_STRING'),
    'namespace'         => env('AZURE_SERVICE_BUS_NAMESPACE'),
    'sas_key_name'      => env('AZURE_SERVICE_BUS_SAS_KEY_NAME', 'RootManageSharedAccessKey'),
    'sas_key'           => env('AZURE_SERVICE_BUS_SAS_KEY'),
    'default_queue'     => env('AZURE_SERVICE_BUS_QUEUE', 'default'),
    'sas_token_ttl'     => env('AZURE_SERVICE_BUS_SAS_TTL', 3600),
    'timeout'           => env('AZURE_SERVICE_BUS_TIMEOUT', 30),
    'retries'           => env('AZURE_SERVICE_BUS_RETRIES', 3),
    'retry_delay_ms'    => env('AZURE_SERVICE_BUS_RETRY_DELAY', 1000),
    'use_dead_letter_on_failure' => env('AZURE_SERVICE_BUS_USE_DLQ', false),
];
```

## Usage (Direct Client / Facade)

### Sending Messages

**Via Facade:**

```php
use Marmanik\AzureServiceBus\Facades\AzureServiceBus;
use Marmanik\AzureServiceBus\ServiceBusMessage;

// Send a simple JSON message to a queue
$message = ServiceBusMessage::create(json_encode(['order_id' => 42]))
    ->withLabel('order-created')
    ->withMessageId('unique-msg-id')
    ->withTimeToLive(3600);

AzureServiceBus::sendToQueue('orders', $message);

// Send to a topic
AzureServiceBus::sendToTopic('notifications', $message);
```

**Via dependency injection:**

```php
use Marmanik\AzureServiceBus\AzureServiceBusClient;
use Marmanik\AzureServiceBus\ServiceBusMessage;

class OrderController extends Controller
{
    public function __construct(private AzureServiceBusClient $bus) {}

    public function store(Request $request): JsonResponse
    {
        $message = ServiceBusMessage::create(json_encode($request->validated()))
            ->withCorrelationId($request->header('X-Correlation-ID', ''));

        $this->bus->sendToQueue('orders', $message);

        return response()->json(['status' => 'queued']);
    }
}
```

### Receiving Messages

**Receive and delete (destructive read):**

```php
$received = AzureServiceBus::receiveAndDelete('orders');

if ($received !== null) {
    $data = $received->getDecodedBody();
    // process $data...
}
```

**Peek-lock (safe processing with explicit complete/abandon):**

```php
$received = AzureServiceBus::peekLock('orders');

if ($received !== null) {
    try {
        $data = $received->getDecodedBody();
        // process $data...

        AzureServiceBus::completeMessage(
            'orders',
            $received->getMessageId(),
            $received->getLockToken()
        );
    } catch (\Throwable $e) {
        // Abandon (retry later)
        AzureServiceBus::abandonMessage(
            'orders',
            $received->getMessageId(),
            $received->getLockToken()
        );

        // Or move to dead-letter immediately
        AzureServiceBus::deadLetterMessage(
            'orders',
            $received->getMessageId(),
            $received->getLockToken(),
            reason: 'ProcessingFailed',
            description: $e->getMessage(),
        );
    }
}
```

### Topic Subscriptions

```php
// Receive from a topic subscription
$received = AzureServiceBus::receiveFromSubscription('notifications', 'email-sub');

if ($received !== null) {
    // process...
    AzureServiceBus::completeSubscriptionMessage(
        'notifications',
        'email-sub',
        $received->getMessageId(),
        $received->getLockToken()
    );
}
```

### Advanced Message Options

```php
use Marmanik\AzureServiceBus\ServiceBusMessage;

$message = ServiceBusMessage::create(json_encode($payload))
    ->withContentType('application/json')
    ->withMessageId('msg-' . Str::uuid())
    ->withCorrelationId('corr-123')
    ->withSessionId('session-abc')
    ->withLabel('my-label')
    ->withTimeToLive(7200)
    ->withScheduledEnqueueTime(now()->addMinutes(5))
    ->withProperty('X-Source', 'my-service')
    ->withProperty('X-Priority', 'high');
```

## Usage (Laravel Queue Driver)

This package implements Laravel's queue driver interface, allowing you to use Azure Service Bus as a drop-in queue backend with `dispatch()`, `Queue::push()`, and `php artisan queue:work`.

### Queue Configuration

In `config/queue.php`, add a connection:

```php
'connections' => [
    // ...
    'azure' => [
        'driver'       => 'azure-service-bus',
        'queue'        => env('AZURE_SERVICE_BUS_QUEUE', 'default'),
        // Option A: connection string
        'connection_string' => env('AZURE_SERVICE_BUS_CONNECTION_STRING'),
        // Option B: individual params
        'namespace'    => env('AZURE_SERVICE_BUS_NAMESPACE'),
        'sas_key_name' => env('AZURE_SERVICE_BUS_SAS_KEY_NAME', 'RootManageSharedAccessKey'),
        'sas_key'      => env('AZURE_SERVICE_BUS_SAS_KEY'),
        // Optional
        'sas_token_ttl'           => 3600,
        'timeout'                 => 30,
        'retries'                 => 3,
        'retry_delay_ms'          => 1000,
        'use_dead_letter_on_failure' => env('AZURE_SERVICE_BUS_USE_DLQ', false),
    ],
],
```

Set the default queue connection in `.env`:

```env
QUEUE_CONNECTION=azure
```

### Dispatching Jobs

```php
// Standard Laravel dispatch
ProcessOrder::dispatch($order);

// Dispatch with delay
ProcessOrder::dispatch($order)->delay(now()->addMinutes(10));

// Dispatch to a specific queue
ProcessOrder::dispatch($order)->onQueue('priority-orders');
```

### Processing Jobs

```bash
php artisan queue:work azure --queue=orders
```

Jobs implement the full Laravel job lifecycle including automatic retries, `delete()` on success, and `release()` (abandon) on failure.

---

## Job Failure & Dead Letter Queue

### Default behaviour — Laravel `failed_jobs`

When `use_dead_letter_on_failure` is `false` (default):

| Event | Azure Service Bus | Laravel |
|---|---|---|
| Job throws, retries remain | `abandonMessage()` — message re-queued, `DeliveryCount` incremented | `JobRetrying` event fired |
| Job throws, max attempts hit | `completeMessage()` — message deleted from queue | `JobFailed` event, row written to `failed_jobs` |
| Job succeeds | `completeMessage()` — message deleted from queue | `JobProcessed` event fired |

Manage failed jobs via Artisan:

```bash
php artisan queue:failed
php artisan queue:retry {id}
php artisan queue:retry all
php artisan queue:flush
```

### Dead Letter Queue (DLQ)

When `use_dead_letter_on_failure` is `true`:

```env
AZURE_SERVICE_BUS_USE_DLQ=true
```

| Event | Azure Service Bus | Laravel |
|---|---|---|
| Job throws, retries remain | `abandonMessage()` — message re-queued | `JobRetrying` event fired |
| Job throws, max attempts hit | `deadLetterMessage()` — message moved to `{queue}/$DeadLetterQueue` | `JobFailed` event fired |
| Job succeeds | `completeMessage()` — message deleted | `JobProcessed` event fired |

Dead-lettered messages carry:
- **`DeadLetterReason`** — the exception class (e.g. `RuntimeException`)
- **`DeadLetterErrorDescription`** — the exception message

They are visible in the **Azure Portal → Service Bus → Queue → Dead-letter** tab and in **Service Bus Explorer**.

#### Reading the DLQ directly

```php
use Marmanik\AzureServiceBus\Facades\AzureServiceBus;

$message = AzureServiceBus::receiveAndDelete('orders/$DeadLetterQueue');

if ($message) {
    $body = $message->getDecodedBody();
    // inspect, log, or re-publish...
}
```

#### Replaying a dead-lettered message

```php
use Marmanik\AzureServiceBus\Facades\AzureServiceBus;
use Marmanik\AzureServiceBus\ServiceBusMessage;

$dlq = AzureServiceBus::peekLock('orders/$DeadLetterQueue');

if ($dlq) {
    // Re-publish the original body back to the main queue
    AzureServiceBus::sendToQueue('orders', ServiceBusMessage::create($dlq->getBody()));

    // Acknowledge the DLQ message
    AzureServiceBus::completeMessage(
        'orders/$DeadLetterQueue',
        $dlq->getMessageId(),
        $dlq->getLockToken(),
    );
}
```

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

Run code style fixer:

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome. Please open an issue first to discuss what you would like to change. Ensure tests pass before submitting a pull request.

## Security

If you discover any security related issues, please email marmaridisn@gmail.com instead of using the issue tracker.

## Credits

- [Nikolaos Marmaridis](https://github.com/marmanik)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
