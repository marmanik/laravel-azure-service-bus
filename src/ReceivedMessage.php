<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus;

class ReceivedMessage
{
    public function __construct(
        private readonly string $body,
        private readonly array $brokerProperties = [],
        private readonly string $lockToken = '',
        private readonly string $contentType = 'application/json',
    ) {}

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDecodedBody(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function getLockToken(): string
    {
        return $this->lockToken;
    }

    public function getMessageId(): ?string
    {
        return isset($this->brokerProperties['MessageId'])
            ? (string) $this->brokerProperties['MessageId']
            : null;
    }

    public function getSequenceNumber(): ?int
    {
        return isset($this->brokerProperties['SequenceNumber'])
            ? (int) $this->brokerProperties['SequenceNumber']
            : null;
    }

    public function getDeliveryCount(): ?int
    {
        return isset($this->brokerProperties['DeliveryCount'])
            ? (int) $this->brokerProperties['DeliveryCount']
            : null;
    }

    public function getEnqueuedTimeUtc(): ?string
    {
        return isset($this->brokerProperties['EnqueuedTimeUtc'])
            ? (string) $this->brokerProperties['EnqueuedTimeUtc']
            : null;
    }

    public function getLabel(): ?string
    {
        return isset($this->brokerProperties['Label'])
            ? (string) $this->brokerProperties['Label']
            : null;
    }

    public function getCorrelationId(): ?string
    {
        return isset($this->brokerProperties['CorrelationId'])
            ? (string) $this->brokerProperties['CorrelationId']
            : null;
    }

    public function getSessionId(): ?string
    {
        return isset($this->brokerProperties['SessionId'])
            ? (string) $this->brokerProperties['SessionId']
            : null;
    }

    public function getBrokerProperties(): array
    {
        return $this->brokerProperties;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
