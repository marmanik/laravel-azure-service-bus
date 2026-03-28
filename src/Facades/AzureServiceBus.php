<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus\Facades;

use Illuminate\Support\Facades\Facade;
use Marmanik\AzureServiceBus\ReceivedMessage;
use Marmanik\AzureServiceBus\ServiceBusMessage;

/**
 * @method static void sendToQueue(string $queue, ServiceBusMessage $message)
 * @method static void sendToTopic(string $topic, ServiceBusMessage $message)
 * @method static ReceivedMessage|null receiveAndDelete(string $queue, int $timeout = 30)
 * @method static ReceivedMessage|null peekLock(string $queue, int $timeout = 30)
 * @method static void completeMessage(string $queue, string $messageId, string $lockToken)
 * @method static void abandonMessage(string $queue, string $messageId, string $lockToken)
 * @method static ReceivedMessage|null receiveFromSubscription(string $topic, string $sub, int $timeout = 30)
 * @method static void completeSubscriptionMessage(string $topic, string $sub, string $msgId, string $lockToken)
 *
 * @see \Marmanik\AzureServiceBus\AzureServiceBusClient
 */
class AzureServiceBus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Marmanik\AzureServiceBus\AzureServiceBusClient::class;
    }
}
