<?php

declare(strict_types=1);

use Marmanik\AzureServiceBus\ReceivedMessage;

it('returns the raw body', function () {
    $msg = new ReceivedMessage('raw body');
    expect($msg->getBody())->toBe('raw body');
});

it('decodes JSON body', function () {
    $msg = new ReceivedMessage('{"key":"value"}');
    expect($msg->getDecodedBody())->toBe(['key' => 'value']);
});

it('returns null for invalid JSON body', function () {
    $msg = new ReceivedMessage('not json');
    expect($msg->getDecodedBody())->toBeNull();
});

it('extracts message ID from broker properties', function () {
    $msg = new ReceivedMessage('{}', ['MessageId' => 'abc-123']);
    expect($msg->getMessageId())->toBe('abc-123');
});

it('extracts delivery count from broker properties', function () {
    $msg = new ReceivedMessage('{}', ['DeliveryCount' => 3]);
    expect($msg->getDeliveryCount())->toBe(3);
});

it('returns lock token', function () {
    $msg = new ReceivedMessage('{}', [], 'lock-token-xyz');
    expect($msg->getLockToken())->toBe('lock-token-xyz');
});
