<?php

declare(strict_types=1);

namespace App\Services\Names;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

final class NormalizeAwardeeNamesOnAppVersion
{
    private const string VERSION_CACHE_KEY = 'awardees.normalized_names_version';

    public function runIfAppVersionChanged(): void
    {
        $version = config('nativephp.version');

        if (! is_string($version) || $version === '') {
            return;
        }

        $cache = Cache::store('database');

        if ($cache->get(self::VERSION_CACHE_KEY) === $version) {
            return;
        }

        $exitCode = Artisan::call('awardees:normalize-names', ['--apply' => true]);

        if ($exitCode === 0) {
            $cache->forever(self::VERSION_CACHE_KEY, $version);
        }
    }
}
