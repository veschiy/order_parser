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
            foreach ([
                'ОВА' => 'ОВ',
                'ЄВА' => 'ЄВ',
                'ІНА' => 'ІН',
                'ИНА' => 'ИН',
            ] as $ending => $nominativeEnding) {
                if (mb_substr($part, -mb_strlen($ending, 'UTF-8'), null, 'UTF-8') === $ending) {
                    return mb_substr($part, 0, -mb_strlen($ending, 'UTF-8'), 'UTF-8').$nominativeEnding;
                }
            }

            return $part;
        }

        if (mb_strlen($part, 'UTF-8') <= 2) {
            return $part;
        }

        $exceptions = [
            'михайла' => 'михайло',
            'павла' => 'павло',
            'данила' => 'данило',
            'дмитра' => 'дмитро',
            'євгенія' => 'євгеній',
            'андрія' => 'андрій',
            'юрія' => 'юрій',
            'сергія' => 'сергій',
            'анатолія' => 'анатолій',
            'тимофія' => 'тимофій',
            'григорія' => 'григорій',
            'геннадія' => 'геннадій',
            'рафаеля' => 'рафаель',
        ];

        $lower = mb_strtolower($part, 'UTF-8');

        if (isset($exceptions[$lower])) {
            $converted = $exceptions[$lower];

            return mb_strtoupper(mb_substr($converted, 0, 1, 'UTF-8'), 'UTF-8')
                .mb_substr($converted, 1, null, 'UTF-8');
        }

        $last = mb_substr($part, -1, null, 'UTF-8');

        if (in_array($last, ['а', 'я'], true)) {
            return mb_substr($part, 0, -1, 'UTF-8');
        }

        if ($last === 'у') {
            return mb_substr($part, 0, -1, 'UTF-8').'а';
        }

        if ($last === 'ю') {
            return mb_substr($part, 0, -1, 'UTF-8').'я';
        }

        return $part;
    }
}
