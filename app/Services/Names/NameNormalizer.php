<?php

declare(strict_types=1);

namespace App\Services\Names;

interface NameNormalizer
{
    public function toNominative(string $name): string;
}
