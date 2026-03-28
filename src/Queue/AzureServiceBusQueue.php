<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Marmanik\AzureServiceBus\AzureServiceBusClient;
use Marmanik\AzureServiceBus\ServiceBusMessage;

class AzureServiceBusQueue extends Queue implements QueueContract
{
    public function __construct(
        private readonly AzureServiceBusClient $client,
        private readonly string $defaultQueue = 'default',
    ) {}

    public function size($queue = null): int
    {
        return $this->client->getMessageCount($this->getQueue($queue));
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            fn ($payload, $queue) => $this->pushRaw($payload, $queue),
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $decoded = json_decode($payload, true);
        $messageId = $decoded['uuid'] ?? $decoded['id'] ?? (string) \Illuminate\Support\Str::uuid();

        $message = ServiceBusMessage::create($payload)
            ->withMessageId($messageId);

        $this->client->sendToQueue($this->getQueue($queue), $message);

        return $messageId;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            fn ($payload, $queue) => $this->pushDelayed($delay, $payload, $this->getQueue($queue)),
        );
    }

    public function pop($queue = null): ?AzureServiceBusJob
    {
        $queueName = $this->getQueue($queue);
        $received = $this->client->peekLock($queueName);

        if ($received === null) {
            return null;
        }

        return new AzureServiceBusJob(
            container: $this->container,
            client: $this->client,
            receivedMessage: $received,
            connectionName: $this->connectionName,
            queue: $queueName,
        );
    }

    protected function getQueue(?string $queue): string
    {
        return $queue ?? $this->defaultQueue;
    }

    private function pushDelayed(mixed $delay, string $payload, string $queue): mixed
    {
        $decoded = json_decode($payload, true);
        $messageId = $decoded['uuid'] ?? $decoded['id'] ?? (string) \Illuminate\Support\Str::uuid();

        $availableAt = $this->availableAt($delay);
        $scheduledTime = \Carbon\Carbon::createFromTimestamp($availableAt);

        $message = ServiceBusMessage::create($payload)
            ->withMessageId($messageId)
            ->withScheduledEnqueueTime($scheduledTime);

        $this->client->sendToQueue($queue, $message);

        return $messageId;
    }
}
