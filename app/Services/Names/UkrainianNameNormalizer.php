<?php

declare(strict_types=1);

namespace App\Services\Names;

final class UkrainianNameNormalizer implements NameNormalizer
{
    public function __construct(private readonly UkrainianNominativeCandidateFinder $candidateFinder = new UkrainianNominativeCandidateFinder) {}

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
            $candidate = $this->candidateFinder->find(mb_strtolower($part, 'UTF-8'), 'givenName', $gender);
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
        $previousCharacter = mb_strtolower(mb_substr($part, -3, 1, 'UTF-8'), 'UTF-8');
        $result = null;

        if ($lastTwo === 'РА' || $lastTwo === 'РО') {
            $result = mb_substr($part, 0, -1, 'UTF-8');
        } elseif ($lastTwo === 'ЛА'
            && in_array($previousCharacter, ['е', 'є', 'и', 'і', 'о', 'у', 'ю', 'я', 'д'], true)) {
            $result = mb_substr($part, 0, -1, 'UTF-8').'О';
        } elseif ($lastTwo === 'ДЛ' && mb_substr($part, -3, 1, 'UTF-8') !== 'О') {
            $result = $part.'О';
        } elseif ($lastTwo === 'НА' && $previousCharacter === 'х') {
            $result = mb_substr($part, 0, -1, 'UTF-8').'О';
        }

        if ($result !== null) {
            return $result;
        }

        $candidate = $this->candidateFinder->find(mb_strtolower($part, 'UTF-8'), 'familyName', $gender);
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
            $candidate = $this->candidateFinder->find(mb_strtolower($part, 'UTF-8'), 'givenName', $gender);
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
}
