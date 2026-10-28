<?php

declare(strict_types=1);

namespace App\Services\Names;

use Shevchenko\Language\GrammaticalGender;
use Shevchenko\Shevchenko;

final class UkrainianNameNormalizer
{
    public function toNominative(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $gender = $this->detectGender($parts[2] ?? '');

        $normalized = [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            $normalized[] = $this->normalizePart($part, $index, $gender);
        }

        return implode(' ', $normalized);
    }

    private function detectGender(string $patronymic): string
    {
        $lower = mb_strtolower($patronymic, 'UTF-8');

        if (str_ends_with($lower, 'івна') || str_ends_with($lower, 'овна')) {
            return 'female';
        }

        return 'male';
    }

    private function normalizePart(string $part, int $index = 0, string $gender = 'male'): string
    {
        $isUppercase = mb_strtoupper($part, 'UTF-8') === $part;

        if ($isUppercase) {
            if ($index === 0 && $gender === 'male') {
                foreach ([
                    'ОГО' => 'ИЙ',
                    'ЕГО' => 'ИЙ',
                    'ОВА' => 'ОВ',
                    'ЄВА' => 'ЄВ',
                    'ІНА' => 'ІН',
                    'ИНА' => 'ИН',
                ] as $ending => $nominativeEnding) {
                    if (mb_substr($part, -mb_strlen($ending, 'UTF-8'), null, 'UTF-8') === $ending) {
                        return mb_substr($part, 0, -mb_strlen($ending, 'UTF-8'), 'UTF-8').$nominativeEnding;
                    }
                }

                if (mb_substr($part, -2, 2, 'UTF-8') === 'КА') {
                    $stem = mb_substr($part, 0, -2, 'UTF-8');
                    $lastStemCharacter = mb_strtolower(mb_substr($stem, -1, 1, 'UTF-8'), 'UTF-8');

                    if (! in_array($lastStemCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)
                        && class_exists(Shevchenko::class)) {
                        try {
                            $shevchenko = new Shevchenko;
                            $candidate = mb_strtolower($stem.'КО', 'UTF-8');

                            if ($shevchenko->inAccusative([
                                'familyName' => $candidate,
                                'gender' => GrammaticalGender::MASCULINE,
                            ])['familyName'] === mb_strtolower($part, 'UTF-8')) {
                                return mb_strtoupper($candidate, 'UTF-8');
                            }
                        } catch (\Throwable) {
                            // Fall through to the generic rules.
                        }
                    }
                }

                $candidate = $this->findNominativeCandidate(mb_strtolower($part, 'UTF-8'), 'familyName', $gender);

                if ($candidate !== null) {
                    return mb_strtoupper($candidate, 'UTF-8');
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

        if ($index === 1) {
            $candidate = $this->findNominativeCandidate(mb_strtolower($part, 'UTF-8'), 'givenName', $gender);

            if ($candidate !== null) {
                return mb_strtoupper(mb_substr($candidate, 0, 1, 'UTF-8'), 'UTF-8')
                    .mb_substr($candidate, 1, null, 'UTF-8');
            }
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

    private function findNominativeCandidate(string $source, string $field, string $gender): ?string
    {
        if (! class_exists(Shevchenko::class)) {
            return null;
        }

        try {
            $shevchenko = new Shevchenko;
            $grammaticalGender = $gender === 'female'
                ? GrammaticalGender::FEMININE
                : GrammaticalGender::MASCULINE;

            foreach ($this->candidateWords($source, $field) as $candidate) {
                if ($candidate === $source) {
                    continue;
                }

                foreach (['inAccusative', 'inGenitive'] as $method) {
                    try {
                        $result = $shevchenko->{$method}([
                            $field => $candidate,
                            'gender' => $grammaticalGender,
                        ]);

                        if (is_string($result[$field]) && mb_strtolower($result[$field], 'UTF-8') === $source) {
                            return $candidate;
                        }
                    } catch (\Throwable) {
                        // Try the next candidate or grammatical case.
                    }
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateWords(string $source, string $field = 'givenName'): array
    {
        $candidates = [$source];
        $last = mb_substr($source, -1, 1, 'UTF-8');
        $stem = mb_substr($source, 0, -1, 'UTF-8');

        if (in_array($last, ['а', 'я', 'у', 'ю', 'і', 'и'], true)) {
            $candidates[] = $stem;
        }

        if ($last === 'у') {
            $candidates[] = $stem.'а';
        }

        if ($last === 'ю') {
            $candidates[] = $stem.'я';
        }

        if ($last === 'а') {
            $candidates[] = $stem.'о';
        }

        if ($last === 'я') {
            $candidates[] = $stem.'й';
        }

        return array_values(array_unique($candidates));
    }
}
