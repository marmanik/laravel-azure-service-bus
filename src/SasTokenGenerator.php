<?php

declare(strict_types=1);

namespace Marmanik\AzureServiceBus;

class SasTokenGenerator
{
    public function __construct(
        private readonly string $namespace,
        private readonly string $sasKeyName,
        private readonly string $sasKey,
        private readonly int $tokenTtl = 3600,
    ) {}

    public function generate(?string $resourceUri = null): string
    {
        $resourceUri ??= "https://{$this->namespace}.servicebus.windows.net/";

        $encodedUri = strtolower(rawurlencode(strtolower($resourceUri)));
        $expiry = time() + $this->tokenTtl;
        $stringToSign = "{$encodedUri}\n{$expiry}";
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $this->sasKey, true));

        return "SharedAccessSignature sr={$encodedUri}&sig=" . rawurlencode($signature) . "&se={$expiry}&skn={$this->sasKeyName}";
    }
}
