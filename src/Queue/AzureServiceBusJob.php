<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Marmanik\AzureServiceBus\AzureServiceBusClient;
use Marmanik\AzureServiceBus\ReceivedMessage;

class AzureServiceBusJob extends Job implements JobContract
{
    private readonly AzureServiceBusClient $client;
    private readonly ReceivedMessage $receivedMessage;

    public function __construct(
        Container $container,
        AzureServiceBusClient $client,
        ReceivedMessage $receivedMessage,
        string $connectionName,
        string $queue,
    ) {
        $this->container = $container;
        $this->client = $client;
        $this->receivedMessage = $receivedMessage;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    public function getJobId(): ?string
    {
        return $this->receivedMessage->getMessageId();
    }

    public function getRawBody(): string
    {
        return $this->receivedMessage->getBody();
    }

    /**
     * Decode the raw body and guarantee the Laravel queue payload keys
     * (`job`, `data`) are always present.
     *
     * Azure Service Bus may carry messages produced outside of Laravel that
     * do not follow the standard queue-payload envelope.  Without this
     * override the base Job class accesses `payload()['job']` directly in
     * fire(), getName(), and failed(), throwing "Undefined array key 'job'"
     * and causing a cascading double-failure in the worker output.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $decoded = json_decode($this->getRawBody(), true);

        if (! is_array($decoded)) {
            $decoded = ['body' => $this->getRawBody()];
        }

        if (! isset($decoded['job'])) {
            $decoded['job'] = 'Illuminate\Queue\CallQueuedHandler@call';
        }

        if (! isset($decoded['data'])) {
            $decoded['data'] = [];
        }

        return $decoded;
    }

    public function attempts(): int
    {
        return $this->receivedMessage->getDeliveryCount() ?? 1;
    }

    public function delete(): void
    {
        parent::delete();

        $messageId = $this->receivedMessage->getMessageId() ?? '';
        $lockToken = $this->receivedMessage->getLockToken();

        $this->client->completeMessage($this->queue, $messageId, $lockToken);
    }

    public function release($delay = 0): void
    {
        parent::release($delay);

        $messageId = $this->receivedMessage->getMessageId() ?? '';
        $lockToken = $this->receivedMessage->getLockToken();

        $this->client->abandonMessage($this->queue, $messageId, $lockToken);
    }
}
