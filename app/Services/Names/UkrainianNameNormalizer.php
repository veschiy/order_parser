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
        if (str_contains($part, '-')) {
            $segments = explode('-', $part);
            $normalized = [];

            foreach ($segments as $segment) {
                $normalized[] = $this->normalizePart($segment, $index, $gender);
            }

            return implode('-', $normalized);
        }

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

                $lastTwo = mb_substr($part, -2, 2, 'UTF-8');

                // Indeclinable masculine surnames ending in -р may acquire
                // a spurious trailing -а/-о (e.g. Білозіра/Білозіро -> Білозір).
                if ($lastTwo === 'РА' || $lastTwo === 'РО') {
                    return mb_substr($part, 0, -1, 'UTF-8');
                }

                // Adjective-type surnames ending in -ло take -ла in the
                // genitive (e.g. Смаровидла -> Смаровидло, Корнила -> Корнило).
                $previousCharacter = mb_strtolower(mb_substr($part, -3, 1, 'UTF-8'), 'UTF-8');

                if ($lastTwo === 'ЛА'
                    && (in_array($previousCharacter, ['е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я', 'д'], true))) {
                    return mb_substr($part, 0, -1, 'UTF-8').'О';
                }

                // Some indeclinable surnames surface without their final -о
                // (e.g. Смаровидл -> Смаровидло).
                if ($lastTwo === 'ДЛ' && mb_substr($part, -3, 1, 'UTF-8') !== 'О') {
                    return $part.'О';
                }

                // Adjective-type surnames ending in -но take -на in the
                // genitive (e.g. Махна -> Махно).
                if ($lastTwo === 'НА'
                    && mb_strtolower(mb_substr($part, -3, 1, 'UTF-8'), 'UTF-8') === 'х') {
                    return mb_substr($part, 0, -1, 'UTF-8').'О';
                }

                $candidate = $this->findNominativeCandidate(mb_strtolower($part, 'UTF-8'), 'familyName', $gender);

                if ($candidate !== null) {
                    $normalizedCandidate = mb_strtoupper($candidate, 'UTF-8');

                    if (mb_substr($normalizedCandidate, -3, 3, 'UTF-8') === 'ОКО') {
                        $normalizedCandidate = mb_substr($normalizedCandidate, 0, -1, 'UTF-8');
                    }

                    // A soft-sign candidate after a vowel is the iotated
                    // nominative (e.g. Гоціь -> Гоцій).
                    if (mb_substr($normalizedCandidate, -1, 1, 'UTF-8') === 'Ь') {
                        $previousCharacter = mb_strtolower(mb_substr($normalizedCandidate, -2, 1, 'UTF-8'), 'UTF-8');

                        if (in_array($previousCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                            $normalizedCandidate = mb_substr($normalizedCandidate, 0, -1, 'UTF-8').'Й';
                        }
                    }

                    return $normalizedCandidate;
                }

                // Genitive/accusative surnames ending in -цй can lose the
                // restored vowel (e.g. Ємця -> Ємцй); recover the nominative.
                if (mb_substr($part, -2, 2, 'UTF-8') === 'ЦЙ') {
                    return mb_substr($part, 0, -2, 'UTF-8').'ЕЦЬ';
                }

                // Soft-sign surnames can be wrongly inflected to -й
                // (e.g. Грозя -> Грозй); recover the nominative -ь.
                if (mb_strlen($part, 'UTF-8') >= 2
                    && mb_substr($part, -1, 1, 'UTF-8') === 'Й') {
                    $previous = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');

                    if (! in_array($previous, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                        return mb_substr($part, 0, -1, 'UTF-8').'Ь';
                    }
                }

                $lastTwo = mb_substr($part, -2, 2, 'UTF-8');

                // Indeclinable masculine surnames ending in -р may acquire
                // a spurious trailing -а/-о (e.g. Білозіра/Білозіро -> Білозір).
                if ($lastTwo === 'РА' || $lastTwo === 'РО') {
                    return mb_substr($part, 0, -1, 'UTF-8');
                }

                // Adjective-type surnames ending in -ло take -ла in the genitive
                // (e.g. Смаровидла -> Смаровидло).
                if ($lastTwo === 'ЛА') {
                    return mb_substr($part, 0, -1, 'UTF-8').'О';
                }
            }

            // An uppercase vowel-final word ending in a soft sign is the
            // iotated nominative (e.g. БУГАЬ -> БУГАЙ).
            if (mb_substr($part, -1, 1, 'UTF-8') === 'Ь') {
                $previousCharacter = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');

                if (in_array($previousCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                    return mb_substr($part, 0, -1, 'UTF-8').'Й';
                }
            }

            // The nominative -є- can also surface as -й- in an already
            // normalized surname (e.g. КОЛОМОЙЦЬ -> КОЛОМОЄЦЬ).
            if (mb_substr($part, -3, 3, 'UTF-8') === 'ЙЦЬ') {
                return mb_substr($part, 0, -3, 'UTF-8').'ЄЦЬ';
            }

            // Some surnames take the -ко ending incorrectly from an extra -о
            // (e.g. Тягнибоко -> Тягнибок).
            if (mb_substr($part, -3, 3, 'UTF-8') === 'ОКО') {
                return mb_substr($part, 0, -1, 'UTF-8');
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

        // Recover the nominative form for surnames that lost a vowel
        // before -ць (e.g. Ємцй -> Ємець).
        if (mb_substr($part, -2, 2, 'UTF-8') === 'цй') {
            return mb_substr($part, 0, -2, 'UTF-8').'ець';
        }

        // Recover soft-sign surnames wrongly inflected to -й (e.g. грозй -> грозь).
        if (mb_substr($part, -1, 1, 'UTF-8') === 'й') {
            $previous = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');

            if (! in_array($previous, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                return mb_substr($part, 0, -1, 'UTF-8').'ь';
            }
        }

        // Masculine surnames ending in -р are indeclinable; trim a spurious
        // trailing -а/-о introduced by case inflection (e.g. Білозіра/Білозіро -> Білозір).
        if ($index === 0) {
            $lastTwo = mb_substr($part, -2, 2, 'UTF-8');

            if (in_array($lastTwo, ['РА', 'РО', 'ра', 'ро'], true)) {
                return mb_substr($part, 0, -1, 'UTF-8');
            }

            // Adjective-type surnames ending in -ло -> -ла in the genitive.
            $previousCharacter = mb_strtolower(mb_substr($part, -3, 1, 'UTF-8'), 'UTF-8');

            if ($lastTwo === 'ла'
                && in_array($previousCharacter, ['е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я', 'д'], true)) {
                return mb_substr($part, 0, -1, 'UTF-8').'о';
            }

            // Bare indeclinable surname stems ending in -дл acquire -о.
            if ($lastTwo === 'дл' && mb_substr($part, -3, 1, 'UTF-8') !== 'о') {
                return $part.'о';
            }

            // Adjective-type surnames ending in -но take -на in the genitive.
            if ($lastTwo === 'на' && mb_substr($part, -3, 1, 'UTF-8') === 'х') {
                return mb_substr($part, 0, -1, 'UTF-8').'о';
            }
        }

        // A trailing soft sign after a vowel is normally the iotated nominative
        // ending (e.g. Віталіь -> Віталій), not a surname stem.
        if (mb_substr($part, -1, 1, 'UTF-8') === 'ь') {
            $previousCharacter = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');

            if (in_array($previousCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                return mb_substr($part, 0, -1, 'UTF-8').'й';
            }
        }

        // The nominative -є- can also surface as -й- in an already
        // normalized surname (e.g. коломойць -> коломоєць).
        if (mb_substr($part, -3, 3, 'UTF-8') === 'йць') {
            return mb_substr($part, 0, -3, 'UTF-8').'єць';
        }

        // Bare surnames can surface with an extra final -о.
        if (mb_substr($part, -3, 3, 'UTF-8') === 'око') {
            return mb_substr($part, 0, -1, 'UTF-8');
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

                // Some indeclinable consonant-final surnames (such as those
                // ending in -р) are not declined correctly by Shevchenko;
                // accept the bare consonant stem when the source differs only
                // by the accusative/genitive -а suffix.
                if (mb_substr($candidate, -1, 1, 'UTF-8') === 'р'
                    && $source === $candidate.'а') {
                    return $candidate;
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

        // Some Ukrainian masculine surnames change -е- to -а- in the
        // genitive (e.g. Орел -> Орла).
        if (mb_substr($source, -2, 2, 'UTF-8') === 'ла') {
            $candidates[] = mb_substr($source, 0, -2, 'UTF-8').'ел';
        }

        // Some Ukrainian surnames lose the stem vowel before -ць in the
        // genitive/accusative (e.g. Ємець -> Ємця); try the restored form
        // before the more aggressive synthetic candidates.
        if (mb_substr($source, -2, 2, 'UTF-8') === 'ця') {
            $candidates[] = mb_substr($source, 0, -2, 'UTF-8').'ець';
        }

        // Nominative -є- alternates with -й- in the genitive/accusative
        // (e.g. Коломоєць -> Коломойця).
        if (mb_substr($source, -3, 3, 'UTF-8') === 'йця') {
            $candidates[] = mb_substr($source, 0, -3, 'UTF-8').'єць';
        }

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
            $previousCharacter = mb_substr($stem, -1, 1, 'UTF-8');
            $vowels = ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'];

            // Surnames ending in -ня are treated as fixed forms and are not
            // converted to soft-sign nominatives.
            if ($previousCharacter !== 'н') {
                // Vowel-final stems take the iotated nominative (Бакай),
                // while consonant-final stems take the soft-sign form (Грозь).
                if (in_array($previousCharacter, $vowels, true)) {
                    $candidates[] = $stem.'й';
                    $candidates[] = $stem.'ь';
                } else {
                    $candidates[] = $stem.'ь';
                    $candidates[] = $stem.'й';
                }
            }
        }

        return array_values(array_unique($candidates));
    }
}
