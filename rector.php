<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        laravel: true,
        phpunit: true,
    );
