<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite') {
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
        }
    }
}
