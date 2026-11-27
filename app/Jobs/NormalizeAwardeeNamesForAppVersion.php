<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Names\NormalizeAwardeeNamesOnAppVersion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
final class NormalizeAwardeeNamesForAppVersion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public string $version) {}

    public function uniqueId(): string
    {
        return $this->version;
    }

    public function handle(NormalizeAwardeeNamesOnAppVersion $nameNormalizer): void
    {
        if (config('nativephp.version') !== $this->version) {
            return;
        }

        $nameNormalizer->runIfAppVersionChanged();
    }
}
