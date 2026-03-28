<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\QueryException;
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
        if ($this->deleted) {
            return;
        }

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

    /**
     * Mark the job as failed and complete (delete) it from Azure Service Bus.
     *
     * Execution order inside parent::fail():
     *   1. markAsFailed()
     *   2. $this->delete()       ← completeMessage() — message removed from Azure
     *   3. $this->failed($e)     ← job's own failed() method
     *   4. dispatch(JobFailed)   ← logFailedJob → INSERT into failed_jobs
     *
     * Step 2 always runs before step 4.  If step 4 throws a UNIQUE constraint
     * violation it means a re-delivered message whose UUID was already recorded
     * as failed in a prior run (before the message was properly completed on
     * Azure).  The message has been completed — absorb the duplicate-insert
     * error so the worker does not log a spurious ERROR for a resolved situation.
     */
    public function fail($e = null): void
    {
        try {
            parent::fail($e);
        } catch (\Throwable $failException) {
            // Defensive: ensure the Azure message is acknowledged if delete()
            // was somehow not reached before the exception was thrown.
            if (! $this->deleted) {
                $this->delete();
            }

            // Silently discard UNIQUE constraint violations on failed_jobs —
            // the job was already recorded in a previous run and the message
            // has now been completed on Azure.
            if ($this->isDuplicateFailedJobException($failException)) {
                return;
            }

            throw $failException;
        }
    }

    /**
     * Return true when the exception is a UNIQUE constraint violation on the
     * failed_jobs table — indicating a re-delivered message whose failure was
     * already logged in a previous worker run.
     */
    private function isDuplicateFailedJobException(\Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        // SQLSTATE 23000 = Integrity Constraint Violation (all major RDBMS)
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'failed_jobs');
    }
}
