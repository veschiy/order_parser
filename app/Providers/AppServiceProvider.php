<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $connections = config('database.connections', []);

            if (! is_array($connections)) {
                return;
            }

            foreach ($connections as $name => $configuration) {
                if (! is_string($name)) {
                    continue;
                }

                try {
                    $connection = DB::connection($name);

                    if ($connection->getDriverName() !== 'sqlite') {
                        continue;
                    }

                    $pdo = $connection->getPdo();

                    $pdo->sqliteCreateFunction(
                        'mb_lower',
                        static fn (?string $value): ?string => $value === null ? null : mb_strtolower($value, 'UTF-8'),
                        1,
                    );

                    $pdo->sqliteCreateFunction(
                        'mb_upper',
                        static fn (?string $value): ?string => $value === null ? null : mb_strtoupper($value, 'UTF-8'),
                        1,
                    );
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {
            return;
        }
    }
}
