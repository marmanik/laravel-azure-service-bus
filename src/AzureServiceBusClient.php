<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Marmanik\AzureServiceBus\Exceptions\AzureServiceBusException;

class AzureServiceBusClient
{
    private readonly SasTokenGenerator $tokenGenerator;
    private readonly string $baseUrl;

    public function __construct(
        private readonly string $namespace,
        private readonly string $sasKeyName,
        private readonly string $sasKey,
        private readonly int $tokenTtl = 3600,
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        private readonly int $retryDelayMs = 1000,
    ) {
        $this->tokenGenerator = new SasTokenGenerator($namespace, $sasKeyName, $sasKey, $tokenTtl);
        $this->baseUrl = "https://{$namespace}.servicebus.windows.net";
    }

    public static function fromConfig(): self
    {
        $connectionString = config('azure-service-bus.connection_string');

        if ($connectionString) {
            $parsed = self::parseConnectionString($connectionString);

            return new self(
                namespace: $parsed['namespace'],
                sasKeyName: $parsed['sasKeyName'],
                sasKey: $parsed['sasKey'],
                tokenTtl: (int) config('azure-service-bus.sas_token_ttl', 3600),
                timeout: (int) config('azure-service-bus.timeout', 30),
                retries: (int) config('azure-service-bus.retries', 3),
                retryDelayMs: (int) config('azure-service-bus.retry_delay_ms', 1000),
            );
        }

        $namespace = config('azure-service-bus.namespace');
        if (! $namespace) {
            throw AzureServiceBusException::missingConfiguration('namespace');
        }

        $sasKeyName = config('azure-service-bus.sas_key_name');
        if (! $sasKeyName) {
            throw AzureServiceBusException::missingConfiguration('sas_key_name');
        }

        $sasKey = config('azure-service-bus.sas_key');
        if (! $sasKey) {
            throw AzureServiceBusException::missingConfiguration('sas_key');
        }

        return new self(
            namespace: $namespace,
            sasKeyName: $sasKeyName,
            sasKey: $sasKey,
            tokenTtl: (int) config('azure-service-bus.sas_token_ttl', 3600),
            timeout: (int) config('azure-service-bus.timeout', 30),
            retries: (int) config('azure-service-bus.retries', 3),
            retryDelayMs: (int) config('azure-service-bus.retry_delay_ms', 1000),
        );
    }

    public static function fromConnectionString(string $connectionString): self
    {
        $parsed = self::parseConnectionString($connectionString);

        return new self(
            namespace: $parsed['namespace'],
            sasKeyName: $parsed['sasKeyName'],
            sasKey: $parsed['sasKey'],
        );
    }

    /**
     * Return the number of active messages waiting in the queue.
     *
     * Calls the Azure Service Bus management REST endpoint:
     *   GET https://{namespace}.servicebus.windows.net/{queue}
     *
     * The response is an Atom XML document whose <QueueDescription> element
     * contains <MessageCountDetails> with an <d2p1:ActiveMessageCount> child
     * that reflects messages available for immediate delivery (excludes
     * scheduled, deferred, and dead-lettered messages).
     */
    public function getMessageCount(string $queue): int
    {
        $url      = "{$this->baseUrl}/{$queue}";
        $response = $this->httpClient()->get($url);

        if (! $response->successful()) {
            return 0;
        }

        $xml = @simplexml_load_string($response->body());

        if ($xml === false) {
            return 0;
        }

        $xml->registerXPathNamespace('d2p1', 'http://schemas.microsoft.com/netservices/2010/10/servicebus/connect');

        // ActiveMessageCount — messages ready for immediate delivery
        $nodes = $xml->xpath('//d2p1:ActiveMessageCount');

        if (! empty($nodes)) {
            return (int) $nodes[0];
        }

        // Fallback: total MessageCount from the root QueueDescription
        $nodes = $xml->xpath('//d2p1:MessageCount');

        return ! empty($nodes) ? (int) $nodes[0] : 0;
    }

    public function sendToQueue(string $queue, ServiceBusMessage $message): void
    {
        $url = "{$this->baseUrl}/{$queue}/messages";
        $this->sendMessage($url, $queue, $message);
    }

    public function sendToTopic(string $topic, ServiceBusMessage $message): void
    {
        $url = "{$this->baseUrl}/{$topic}/messages";
        $this->sendMessage($url, $topic, $message);
    }

    public function receiveAndDelete(string $queue, int $timeout = 30): ?ReceivedMessage
    {
        $url = "{$this->baseUrl}/{$queue}/messages/head?timeout={$timeout}";
        $response = $this->httpClient()->delete($url);

        if ($response->status() === 204 || $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw AzureServiceBusException::receiveFailed($queue, $response->status(), $response->body());
        }

        return $this->parseResponse($response);
    }

    public function peekLock(string $queue, int $timeout = 30): ?ReceivedMessage
    {
        $url = "{$this->baseUrl}/{$queue}/messages/head?timeout={$timeout}";
        $response = $this->httpClient()->post($url, []);

        if ($response->status() === 204 || $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw AzureServiceBusException::receiveFailed($queue, $response->status(), $response->body());
        }

        return $this->parseResponse($response);
    }

    public function completeMessage(string $queue, string $messageId, string $lockToken): void
    {
        $url = "{$this->baseUrl}/{$queue}/messages/{$messageId}/{$lockToken}";
        $response = $this->httpClient()->delete($url);

        if (! $response->successful()) {
            throw AzureServiceBusException::deleteFailed($queue, $messageId, $lockToken);
        }
    }

    public function abandonMessage(string $queue, string $messageId, string $lockToken): void
    {
        $url = "{$this->baseUrl}/{$queue}/messages/{$messageId}/{$lockToken}";
        $response = $this->httpClient()->put($url, []);

        if (! $response->successful()) {
            throw AzureServiceBusException::deleteFailed($queue, $messageId, $lockToken);
        }
    }

    public function receiveFromSubscription(string $topic, string $sub, int $timeout = 30): ?ReceivedMessage
    {
        $url = "{$this->baseUrl}/{$topic}/subscriptions/{$sub}/messages/head?timeout={$timeout}";
        $response = $this->httpClient()->post($url, []);

        if ($response->status() === 204 || $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw AzureServiceBusException::receiveFailed("{$topic}/subscriptions/{$sub}", $response->status(), $response->body());
        }

        return $this->parseResponse($response);
    }

    public function completeSubscriptionMessage(string $topic, string $sub, string $msgId, string $lockToken): void
    {
        $url = "{$this->baseUrl}/{$topic}/subscriptions/{$sub}/messages/{$msgId}/{$lockToken}";
        $response = $this->httpClient()->delete($url);

        if (! $response->successful()) {
            throw AzureServiceBusException::deleteFailed("{$topic}/subscriptions/{$sub}", $msgId, $lockToken);
        }
    }

    private function sendMessage(string $url, string $destination, ServiceBusMessage $message): void
    {
        $headers = ['Content-Type' => $message->getContentType()];

        $brokerProperties = $message->getBrokerProperties();
        if (! empty($brokerProperties)) {
            $headers['BrokerProperties'] = json_encode($brokerProperties);
        }

        foreach ($message->getCustomProperties() as $key => $value) {
            $headers[$key] = $value;
        }

        $response = $this->httpClient()
            ->withHeaders($headers)
            ->withBody($message->getBody(), $message->getContentType())
            ->post($url);

        if (! $response->successful()) {
            throw AzureServiceBusException::sendFailed($destination, $response->status(), $response->body());
        }
    }

    private function parseResponse(\Illuminate\Http\Client\Response $response): ReceivedMessage
    {
        $brokerPropertiesHeader = $response->header('BrokerProperties');
        $brokerProperties = $brokerPropertiesHeader ? (json_decode($brokerPropertiesHeader, true) ?? []) : [];

        $lockToken = '';
        if (isset($brokerProperties['LockToken'])) {
            $lockToken = (string) $brokerProperties['LockToken'];
        } else {
            $locationHeader = $response->header('Location');
            if ($locationHeader && preg_match('/\/([^\/]+)$/', $locationHeader, $matches)) {
                $lockToken = $matches[1];
            }
        }

        $contentType = $response->header('Content-Type') ?? 'application/json';

        return new ReceivedMessage(
            body: $response->body(),
            brokerProperties: $brokerProperties,
            lockToken: $lockToken,
            contentType: $contentType,
        );
    }

    private function httpClient(): PendingRequest
    {
        $token = $this->tokenGenerator->generate($this->baseUrl);

        return Http::withHeaders(['Authorization' => $token])
            ->timeout($this->timeout)
            ->retry($this->retries, $this->retryDelayMs);
    }

    private static function parseConnectionString(string $connectionString): array
    {
        $parts = explode(';', $connectionString);
        $params = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $segments = explode('=', $part, 2);
            if (count($segments) === 2) {
                $params[$segments[0]] = $segments[1];
            }
        }

        if (! isset($params['Endpoint'])) {
            throw AzureServiceBusException::connectionFailed('Missing Endpoint in connection string');
        }

        if (! isset($params['SharedAccessKeyName'])) {
            throw AzureServiceBusException::connectionFailed('Missing SharedAccessKeyName in connection string');
        }

        if (! isset($params['SharedAccessKey'])) {
            throw AzureServiceBusException::connectionFailed('Missing SharedAccessKey in connection string');
        }

        $endpoint = $params['Endpoint'];
        $host = parse_url($endpoint, PHP_URL_HOST) ?? $endpoint;
        $namespace = str_replace('.servicebus.windows.net', '', $host);

        return [
            'namespace'  => $namespace,
            'sasKeyName' => $params['SharedAccessKeyName'],
            'sasKey'     => $params['SharedAccessKey'],
        ];
    }
}
