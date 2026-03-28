<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Exceptions;

use Exception;

class AzureServiceBusException extends Exception
{
    public static function connectionFailed(string $reason): self
    {
        return new self("Azure Service Bus connection failed: {$reason}");
    }

    public static function sendFailed(string $queue, int $statusCode, string $body): self
    {
        return new self("Failed to send message to '{$queue}'. HTTP {$statusCode}: {$body}");
    }

    public static function receiveFailed(string $queue, int $statusCode, string $body): self
    {
        return new self("Failed to receive message from '{$queue}'. HTTP {$statusCode}: {$body}");
    }

    public static function deleteFailed(string $queue, string $messageId, string $lockToken): self
    {
        return new self("Failed to delete message '{$messageId}' with lock '{$lockToken}' from '{$queue}'");
    }

    public static function missingConfiguration(string $key): self
    {
        return new self("Missing Azure Service Bus configuration: '{$key}'");
    }

    public static function deadLetterFailed(string $queue, string $messageId, string $lockToken): self
    {
        return new self("Failed to dead-letter message '{$messageId}' with lock '{$lockToken}' from '{$queue}'");
    }
}
