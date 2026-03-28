<?php

declare(strict_types=1);

use Marmanik\AzureServiceBus\SasTokenGenerator;

it('generates a valid SAS token format', function () {
    $generator = new SasTokenGenerator('test-ns', 'TestKey', 'secret', 3600);
    $token = $generator->generate();

    expect($token)
        ->toStartWith('SharedAccessSignature')
        ->toContain('sr=')
        ->toContain('sig=')
        ->toContain('se=')
        ->toContain('skn=TestKey');
});

it('uses the provided resource URI', function () {
    $generator = new SasTokenGenerator('test-ns', 'TestKey', 'secret', 3600);
    $uri = 'https://test-ns.servicebus.windows.net/my-queue';
    $token = $generator->generate($uri);

    $encodedUri = strtolower(rawurlencode(strtolower($uri)));
    expect($token)->toContain("sr={$encodedUri}");
});

it('defaults resource URI to namespace endpoint', function () {
    $generator = new SasTokenGenerator('my-namespace', 'TestKey', 'secret', 3600);
    $token = $generator->generate();

    $defaultUri = 'https://my-namespace.servicebus.windows.net/';
    $encodedUri = strtolower(rawurlencode(strtolower($defaultUri)));
    expect($token)->toContain("sr={$encodedUri}");
});

it('sets expiry based on TTL', function () {
    $ttl = 3600;
    $before = time();
    $generator = new SasTokenGenerator('test-ns', 'TestKey', 'secret', $ttl);
    $token = $generator->generate();
    $after = time();

    preg_match('/se=(\d+)/', $token, $matches);
    $expiry = (int) $matches[1];

    expect($expiry)->toBeGreaterThanOrEqual($before + $ttl)
        ->toBeLessThanOrEqual($after + $ttl);
});
