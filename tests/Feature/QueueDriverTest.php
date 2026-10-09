<?php

declare(strict_types=1);

use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Http;
use Marmanik\AzureServiceBus\Queue\AzureServiceBusJob;
use Marmanik\AzureServiceBus\Queue\AzureServiceBusQueue;

it('registers the azure-service-bus queue connector', function () {
    $manager = app(QueueManager::class);

    config()->set('queue.connections.azure-test', [
        'driver'      => 'azure-service-bus',
        'queue'       => 'test-queue',
        'namespace'   => 'test-namespace',
        'sas_key_name' => 'TestKeyName',
        'sas_key'     => 'dGVzdC1rZXktdmFsdWU=',
    ]);

    $connection = $manager->connection('azure-test');
    expect($connection)->toBeInstanceOf(AzureServiceBusQueue::class);
});

it('pushes a job to the queue', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/test-queue/messages' => Http::response('', 201),
    ]);

    config()->set('queue.connections.azure-push-test', [
        'driver'       => 'azure-service-bus',
        'queue'        => 'test-queue',
        'namespace'    => 'test-namespace',
        'sas_key_name' => 'TestKeyName',
        'sas_key'      => 'dGVzdC1rZXktdmFsdWU=',
    ]);

    $manager = app(QueueManager::class);
    $queue = $manager->connection('azure-push-test');
    $queue->pushRaw('{"test":"payload"}', 'test-queue');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'test-queue/messages'));
});

it('pops a job from the queue', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/test-queue/messages/head*' => Http::response(
            '{"job":"App\\Jobs\\TestJob","data":{},"uuid":"test-uuid"}',
            200,
            ['BrokerProperties' => '{"MessageId":"msg-pop-1","LockToken":"lock-pop-1","DeliveryCount":1}'],
        ),
    ]);

    config()->set('queue.connections.azure-pop-test', [
        'driver'       => 'azure-service-bus',
        'queue'        => 'test-queue',
        'namespace'    => 'test-namespace',
        'sas_key_name' => 'TestKeyName',
        'sas_key'      => 'dGVzdC1rZXktdmFsdWU=',
    ]);

    $manager = app(QueueManager::class);
    $queue = $manager->connection('azure-pop-test');
    $job = $queue->pop('test-queue');

    expect($job)->toBeInstanceOf(AzureServiceBusJob::class)
        ->and($job->getJobId())->toBe('msg-pop-1');
});

it('reports pending, delayed and reserved sizes', function () {
    Http::fake([
        'https://test-namespace.servicebus.windows.net/test-queue' => Http::response(
            '<entry xmlns="http://www.w3.org/2005/Atom"><content type="application/xml">'
            .'<QueueDescription xmlns="http://schemas.microsoft.com/netservices/2010/10/servicebus/connect">'
            .'<MessageCount>10</MessageCount>'
            .'<CountDetails xmlns:d2p1="http://schemas.microsoft.com/netservices/2011/06/servicebus">'
            .'<d2p1:ActiveMessageCount>5</d2p1:ActiveMessageCount>'
            .'<d2p1:DeadLetterMessageCount>2</d2p1:DeadLetterMessageCount>'
            .'<d2p1:ScheduledMessageCount>3</d2p1:ScheduledMessageCount>'
            .'</CountDetails>'
            .'</QueueDescription></content></entry>',
            200,
        ),
    ]);

    config()->set('queue.connections.azure-size-test', [
        'driver'       => 'azure-service-bus',
        'queue'        => 'test-queue',
        'namespace'    => 'test-namespace',
        'sas_key_name' => 'TestKeyName',
        'sas_key'      => 'dGVzdC1rZXktdmFsdWU=',
    ]);

    $queue = app(QueueManager::class)->connection('azure-size-test');

    expect($queue->size())->toBe(5)
        ->and($queue->pendingSize())->toBe(5)
        ->and($queue->delayedSize())->toBe(3)
        ->and($queue->reservedSize())->toBe(0)
        ->and($queue->creationTimeOfOldestPendingJob())->toBeNull();
});
