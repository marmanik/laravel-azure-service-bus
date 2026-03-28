<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus;

class ServiceBusMessage
{
    private array $brokerProperties = [];
    private array $customProperties = [];

    public function __construct(
        private string $body,
        private string $contentType = 'application/json',
    ) {}

    public static function create(string $body): self
    {
        return new self($body);
    }

    public function withContentType(string $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function withLabel(string $label): self
    {
        $this->brokerProperties['Label'] = $label;

        return $this;
    }

    public function withTimeToLive(int $seconds): self
    {
        $this->brokerProperties['TimeToLive'] = $seconds;

        return $this;
    }

    public function withMessageId(string $messageId): self
    {
        $this->brokerProperties['MessageId'] = $messageId;

        return $this;
    }

    public function withCorrelationId(string $correlationId): self
    {
        $this->brokerProperties['CorrelationId'] = $correlationId;

        return $this;
    }

    public function withSessionId(string $sessionId): self
    {
        $this->brokerProperties['SessionId'] = $sessionId;

        return $this;
    }

    public function withScheduledEnqueueTime(\DateTimeInterface $dateTime): self
    {
        $this->brokerProperties['ScheduledEnqueueTimeUtc'] = $dateTime->format('D, d M Y H:i:s \G\M\T');

        return $this;
    }

    public function withProperty(string $key, string $value): self
    {
        $this->customProperties[$key] = $value;

        return $this;
    }

    public function withBrokerProperties(array $properties): self
    {
        $this->brokerProperties = array_merge($this->brokerProperties, $properties);

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getBrokerProperties(): array
    {
        return $this->brokerProperties;
    }

    public function getCustomProperties(): array
    {
        return $this->customProperties;
    }
}
