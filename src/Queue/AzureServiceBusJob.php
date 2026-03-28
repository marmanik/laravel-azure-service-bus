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
