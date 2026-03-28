<?php

declare(strict_types=1);

arch('source files use strict types')
    ->expect('Marmanik\\AzureServiceBus')
    ->toUseStrictTypes();

arch('exceptions extend base exception')
    ->expect('Marmanik\\AzureServiceBus\\Exceptions')
    ->toExtend(Exception::class);

arch('no debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
