<?php

declare(strict_types=1);

use Marmanik\AzureServiceBus\ServiceBusMessage;

it('creates a message with body', function () {
    $message = new ServiceBusMessage('hello world');
    expect($message->getBody())->toBe('hello world');
});

it('defaults content type to application/json', function () {
    $message = new ServiceBusMessage('{}');
    expect($message->getContentType())->toBe('application/json');
});

it('sets broker properties via fluent methods', function () {
    $message = ServiceBusMessage::create('{}')
        ->withLabel('test-label')
        ->withTimeToLive(60)
        ->withMessageId('msg-123')
        ->withCorrelationId('corr-456')
        ->withSessionId('session-789');

    $props = $message->getBrokerProperties();

    expect($props['Label'])->toBe('test-label')
        ->and($props['TimeToLive'])->toBe(60)
        ->and($props['MessageId'])->toBe('msg-123')
        ->and($props['CorrelationId'])->toBe('corr-456')
        ->and($props['SessionId'])->toBe('session-789');
});

it('sets custom properties', function () {
    $message = ServiceBusMessage::create('{}')
        ->withProperty('X-Custom', 'value123');

    expect($message->getCustomProperties()['X-Custom'])->toBe('value123');
});

it('sets scheduled enqueue time', function () {
    $dt = new \DateTimeImmutable('2024-01-15 10:00:00', new \DateTimeZone('UTC'));
    $message = ServiceBusMessage::create('{}')->withScheduledEnqueueTime($dt);

    $props = $message->getBrokerProperties();
    expect($props)->toHaveKey('ScheduledEnqueueTimeUtc');
    expect($props['ScheduledEnqueueTimeUtc'])->toBeString();
});
