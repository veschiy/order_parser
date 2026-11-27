<?php

declare(strict_types=1);

namespace App\Services\Names;

use Shevchenko\Language\GrammaticalGender;
use Shevchenko\Shevchenko;

final class UkrainianNameNormalizer implements NameNormalizer
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
        $part = preg_replace('/[\x{2010}\x{2011}\x{2012}]/u', '-', $part) ?? $part;
        $part = preg_replace('/(\p{L})\s*-\s*(\p{L})/u', '\1-\2', $part) ?? $part;

        if (str_contains($part, '-')) {
            return $this->normalizeHyphenatedPart($part, $index, $gender);
        }

        $isUppercase = mb_strtoupper($part, 'UTF-8') === $part;

        if ($isUppercase) {
            return $this->normalizeUppercasePart($part, $index, $gender);
        }

        return $this->normalizeLowercasePart($part, $index, $gender);
    }

    private function normalizeHyphenatedPart(string $part, int $index, string $gender): string
    {
        $segments = explode('-', $part);
        $normalized = [];

        foreach ($segments as $segment) {
            $normalized[] = $this->normalizePart($segment, $index, $gender);
        }

        return implode('-', $normalized);
    }

    private function normalizeUppercasePart(string $part, int $index, string $gender): string
    {
        $result = null;

        if ($index === 0 && $gender === 'male') {
            $result = $this->tryUppercaseSurnameRules($part);
        }

        if ($result === null && mb_substr($part, -1, 1, 'UTF-8') === 'Ь') {
            $previousCharacter = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');
            if (in_array($previousCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                $result = mb_substr($part, 0, -1, 'UTF-8').'Й';
            }
        }

        if ($result === null && $index !== 0) {
            $candidate = $this->findNominativeCandidate(mb_strtolower($part, 'UTF-8'), 'givenName', $gender);
            if ($candidate !== null) {
                $result = mb_strtoupper($candidate, 'UTF-8');
            }
        }

        $result ??= $this->applyUppercaseFallbackRules($part);

        return $result ?? $part;
    }

    private function tryUppercaseSurnameRules(string $part): ?string
    {
        $result = $this->applyUppercaseGenitiveEndings($part);
        if ($result !== null) {
            return $result;
        }

        $result = $this->applyUppercaseKaEnding($part);
        if ($result !== null) {
            return $result;
        }

        return $this->applyUppercaseFamilyNameCandidate($part, 'male');
    }

    private function applyUppercaseFallbackRules(string $part): ?string
    {
        if (mb_substr($part, -3, 3, 'UTF-8') === 'ЙЦЬ') {
            return mb_substr($part, 0, -3, 'UTF-8').'ЄЦЬ';
        }

        if (mb_substr($part, -3, 3, 'UTF-8') === 'ОКО') {
            return mb_substr($part, 0, -1, 'UTF-8');
        }

        return null;
    }

    private function applyUppercaseGenitiveEndings(string $part): ?string
    {
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

        return null;
    }

    private function applyUppercaseKaEnding(string $part): ?string
    {
        if (mb_substr($part, -2, 2, 'UTF-8') !== 'КА') {
            return null;
        }

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

        return null;
    }

    private function applyUppercaseFamilyNameCandidate(string $part, string $gender): ?string
    {
        $lastTwo = mb_substr($part, -2, 2, 'UTF-8');

        if ($lastTwo === 'РА' || $lastTwo === 'РО') {
            return mb_substr($part, 0, -1, 'UTF-8');
        }

        $previousCharacter = mb_strtolower(mb_substr($part, -3, 1, 'UTF-8'), 'UTF-8');

        if ($lastTwo === 'ЛА'
            && in_array($previousCharacter, ['е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я', 'д'], true)) {
            return mb_substr($part, 0, -1, 'UTF-8').'О';
        }

        if ($lastTwo === 'ДЛ' && mb_substr($part, -3, 1, 'UTF-8') !== 'О') {
            return $part.'О';
        }

        if ($lastTwo === 'НА' && $previousCharacter === 'х') {
            return mb_substr($part, 0, -1, 'UTF-8').'О';
        }

        $result = null;

        $candidate = $this->findNominativeCandidate(mb_strtolower($part, 'UTF-8'), 'familyName', $gender);
        if ($candidate !== null) {
            $result = $this->normalizeUppercaseCandidate($candidate);
        }

        $result ??= $this->applyUppercaseSoftSignRecovery($part);

        if ($result === null && $lastTwo === 'ЛА') {
            $result = mb_substr($part, 0, -1, 'UTF-8').'О';
        }

        return $result;
    }

    private function normalizeUppercaseCandidate(string $candidate): string
    {
        $normalized = mb_strtoupper($candidate, 'UTF-8');

        if (mb_substr($normalized, -3, 3, 'UTF-8') === 'ОКО') {
            $normalized = mb_substr($normalized, 0, -1, 'UTF-8');
        }

        if (mb_substr($normalized, -1, 1, 'UTF-8') === 'Ь') {
            $previousCharacter = mb_strtolower(mb_substr($normalized, -2, 1, 'UTF-8'), 'UTF-8');
            if (in_array($previousCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                $normalized = mb_substr($normalized, 0, -1, 'UTF-8').'Й';
            }
        }

        return $normalized;
    }

    private function applyUppercaseSoftSignRecovery(string $part): ?string
    {
        if (mb_substr($part, -2, 2, 'UTF-8') === 'ЦЙ') {
            return mb_substr($part, 0, -2, 'UTF-8').'ЕЦЬ';
        }

        if (mb_strlen($part, 'UTF-8') >= 2 && mb_substr($part, -1, 1, 'UTF-8') === 'Й') {
            $previous = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');
            if (! in_array($previous, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                return mb_substr($part, 0, -1, 'UTF-8').'Ь';
            }
        }

        return null;
    }

    private function normalizeLowercasePart(string $part, int $index, string $gender): string
    {
        if (mb_strlen($part, 'UTF-8') <= 2) {
            return $part;
        }

        $result = $this->applyLowercaseExceptions($part);

        if ($result === null && $index === 1) {
            $candidate = $this->findNominativeCandidate(mb_strtolower($part, 'UTF-8'), 'givenName', $gender);
            if ($candidate !== null) {
                $result = mb_strtoupper(mb_substr($candidate, 0, 1, 'UTF-8'), 'UTF-8')
                    .mb_substr($candidate, 1, null, 'UTF-8');
            }
        }

        $result ??= $this->applyLowercaseEndingRules($part);
        $result ??= $this->applyLowercaseSoftSignRules($part);

        if ($result === null && $index === 0) {
            $result = $this->applyLowercaseSurnameRules($part);
        }

        $result ??= $this->applyLowercaseFallbackRules($part);

        return $result ?? $part;
    }

    private function applyLowercaseFallbackRules(string $part): ?string
    {
        $result = null;

        if (mb_substr($part, -1, 1, 'UTF-8') === 'ь') {
            $previousCharacter = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');
            if (in_array($previousCharacter, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                $result = mb_substr($part, 0, -1, 'UTF-8').'й';
            }
        }

        if ($result === null) {
            $lastThree = mb_substr($part, -3, 3, 'UTF-8');

            if ($lastThree === 'йць') {
                $result = mb_substr($part, 0, -3, 'UTF-8').'єць';
            } elseif ($lastThree === 'око') {
                $result = mb_substr($part, 0, -1, 'UTF-8');
            }
        }

        return $result;
    }

    private function applyLowercaseExceptions(string $part): ?string
    {
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

        return null;
    }

    private function applyLowercaseEndingRules(string $part): ?string
    {
        $last = mb_substr($part, -1, null, 'UTF-8');
        $stem = mb_substr($part, 0, -1, 'UTF-8');

        $endingMap = [
            'а' => '',
            'я' => '',
            'у' => 'а',
            'ю' => 'я',
        ];

        if (isset($endingMap[$last])) {
            return $stem.$endingMap[$last];
        }

        // Recover the nominative form for surnames that lost a vowel
        // before -ць (e.g. Ємцй -> Ємець).
        if (mb_substr($part, -2, 2, 'UTF-8') === 'цй') {
            return mb_substr($part, 0, -2, 'UTF-8').'ець';
        }

        return null;
    }

    private function applyLowercaseSoftSignRules(string $part): ?string
    {
        // Recover soft-sign surnames wrongly inflected to -й (e.g. грозй -> грозь).
        if (mb_substr($part, -1, 1, 'UTF-8') === 'й') {
            $previous = mb_strtolower(mb_substr($part, -2, 1, 'UTF-8'), 'UTF-8');

            if (! in_array($previous, ['а', 'е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я'], true)) {
                return mb_substr($part, 0, -1, 'UTF-8').'ь';
            }
        }

        return null;
    }

    private function applyLowercaseSurnameRules(string $part): ?string
    {
        $lastTwo = mb_substr($part, -2, 2, 'UTF-8');

        // Masculine surnames ending in -р are indeclinable; trim a spurious
        // trailing -а/-о introduced by case inflection (e.g. Білозіра/Білозіро -> Білозір).
        if (in_array($lastTwo, ['РА', 'РО', 'ра', 'ро'], true)) {
            return mb_substr($part, 0, -1, 'UTF-8');
        }

        $result = null;

        // Adjective-type surnames ending in -ло -> -ла in the genitive.
        $previousCharacter = mb_strtolower(mb_substr($part, -3, 1, 'UTF-8'), 'UTF-8');

        if ($lastTwo === 'ла'
            && in_array($previousCharacter, ['е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я', 'д'], true)) {
            $result = mb_substr($part, 0, -1, 'UTF-8').'о';
        }

        // Bare indeclinable surname stems ending in -дл acquire -о.
        if ($result === null && $lastTwo === 'дл' && mb_substr($part, -3, 1, 'UTF-8') !== 'о') {
            $result = $part.'о';
        }

        // Adjective-type surnames ending in -но take -на in the genitive.
        if ($result === null && $lastTwo === 'на' && mb_substr($part, -3, 1, 'UTF-8') === 'х') {
            $result = mb_substr($part, 0, -1, 'UTF-8').'о';
        }

        return $result;
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

            foreach ($this->candidateWords($source) as $candidate) {
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
    private function candidateWords(string $source): array
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
