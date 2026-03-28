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

    /** Set to true inside fail() so delete() knows to dead-letter instead of complete. */
    private bool $isFailing = false;
    private string $failReason = 'JobFailed';
    private string $failDescription = 'Job permanently failed';

    public function __construct(
        Container $container,
        AzureServiceBusClient $client,
        ReceivedMessage $receivedMessage,
        string $connectionName,
        string $queue,
        private readonly bool $useDeadLetterOnFailure = false,
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
        if ($this->deleted) {
            return;
        }

        parent::delete();

        $messageId = $this->receivedMessage->getMessageId() ?? '';
        $lockToken = $this->receivedMessage->getLockToken();

        if ($this->isFailing && $this->useDeadLetterOnFailure) {
            $this->client->deadLetterMessage(
                $this->queue,
                $messageId,
                $lockToken,
                $this->failReason,
                $this->failDescription,
            );
        } else {
            $this->client->completeMessage($this->queue, $messageId, $lockToken);
        }
    }

    public function release($delay = 0): void
    {
        parent::release($delay);

        $messageId = $this->receivedMessage->getMessageId() ?? '';
        $lockToken = $this->receivedMessage->getLockToken();

        $this->client->abandonMessage($this->queue, $messageId, $lockToken);
    }

    /**
     * Mark the job as failed and either move it to the Dead Letter Queue or
     * complete (delete) it from Azure Service Bus.
     *
     * Laravel's base Job::fail() always calls $this->delete() internally in
     * its finally block — so we must signal the desired disposition BEFORE
     * calling parent::fail(), not after.  We set $isFailing + capture the
     * exception details, then let delete() route to deadLetterMessage() or
     * completeMessage() accordingly.
     *
     * Call chain:
     *   fail($e)
     *     → parent::fail($e)
     *         → $this->delete()   ← dispatches to Azure (DLQ or complete)
     */
    public function fail($e = null): void
    {
        $this->isFailing       = true;
        $this->failReason      = $e !== null ? get_class($e) : 'JobFailed';
        $this->failDescription = $e !== null ? $e->getMessage() : 'Job permanently failed';

        parent::fail($e);
    }
}
