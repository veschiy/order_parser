<?php

declare(strict_types=1);

namespace App\Services\Names;

final class UkrainianNameNormalizer
{
    public function toNominative(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $normalized[] = $this->normalizePart($part);
        }

        return implode(' ', $normalized);
    }

    private function normalizePart(string $part): string
    {
        if (mb_strtoupper($part, 'UTF-8') === $part) {
            return $part;
        }

        if (mb_strlen($part, 'UTF-8') <= 2) {
            return $part;
        }

        $last = mb_substr($part, -1, null, 'UTF-8');

        if (in_array($last, ['а', 'я'], true)) {
            return mb_substr($part, 0, -1, 'UTF-8');
        }

        return $part;
    }
}
