<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Marmanik\AzureServiceBus\AzureServiceBusClient;
use Marmanik\AzureServiceBus\Exceptions\AzureServiceBusException;
use Marmanik\AzureServiceBus\ServiceBusMessage;

it('builds from connection string', function () {
    $cs = 'Endpoint=sb://myns.servicebus.windows.net/;SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=abc123=';
    $client = AzureServiceBusClient::fromConnectionString($cs);
    expect($client)->toBeInstanceOf(AzureServiceBusClient::class);
});

it('throws on invalid connection string', function () {
    expect(fn () => AzureServiceBusClient::fromConnectionString('invalid'))
        ->toThrow(AzureServiceBusException::class);
});

it('sends message to queue', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/my-queue/messages' => Http::response('', 201),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret');
    $message = ServiceBusMessage::create('{"test":true}');

    $client->sendToQueue('my-queue', $message);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://test-namespace.servicebus.windows.net/my-queue/messages'
            && $request->method() === 'POST';
    });
});

it('receives and deletes message', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/my-queue/messages/head*' => Http::response(
            '{"order_id":1}',
            200,
            ['BrokerProperties' => '{"MessageId":"msg-1","LockToken":"lock-1","DeliveryCount":1}'],
        ),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret');
    $received = $client->receiveAndDelete('my-queue');

    expect($received)->not->toBeNull()
        ->and($received->getMessageId())->toBe('msg-1');
});

it('peek-locks a message', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/my-queue/messages/head*' => Http::response(
            '{"data":"test"}',
            200,
            ['BrokerProperties' => '{"MessageId":"msg-2","LockToken":"lock-2"}'],
        ),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret');
    $received = $client->peekLock('my-queue');

    Http::assertSent(fn ($request) => $request->method() === 'POST');
    expect($received)->not->toBeNull();
});

it('returns null when queue is empty', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/empty-queue/messages/head*' => Http::response('', 204),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret');
    $received = $client->peekLock('empty-queue');

    expect($received)->toBeNull();
});

it('completes a message', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/my-queue/messages/msg-1/lock-1' => Http::response('', 200),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret');
    $client->completeMessage('my-queue', 'msg-1', 'lock-1');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});

it('abandons a message', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/my-queue/messages/msg-1/lock-1' => Http::response('', 200),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret');
    $client->abandonMessage('my-queue', 'msg-1', 'lock-1');

    Http::assertSent(fn ($request) => $request->method() === 'PUT');
});

it('throws on send failure', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/my-queue/messages' => Http::response('Internal Server Error', 500),
    ]);

    $client = new AzureServiceBusClient('test-namespace', 'TestKey', 'secret', retries: 1);
    $message = ServiceBusMessage::create('{}');

    expect(fn () => $client->sendToQueue('my-queue', $message))
        ->toThrow(AzureServiceBusException::class);
});
