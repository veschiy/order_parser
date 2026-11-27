<?php

declare(strict_types=1);

namespace App\Services\Names;

use Shevchenko\Language\GrammaticalGender;
use Shevchenko\Shevchenko;

final class UkrainianNominativeCandidateFinder
{
    public function find(string $source, string $field, string $gender): ?string
    {
        if (! class_exists(Shevchenko::class)) {
            return null;
        }

        try {
            return $this->searchNominativeCandidate(new Shevchenko, $source, $field, $gender);
        } catch (\Throwable) {
            return null;
        }
    }

    private function searchNominativeCandidate(Shevchenko $shevchenko, string $source, string $field, string $gender): ?string
    {
        $grammaticalGender = $gender === 'female'
            ? GrammaticalGender::FEMININE
            : GrammaticalGender::MASCULINE;

        foreach ($this->candidateWords($source) as $candidate) {
            if ($candidate === $source) {
                continue;
            }

            // Some indeclinable consonant-final surnames are not declined correctly by the library.
            if (mb_substr($candidate, -1, 1, 'UTF-8') === 'р' && $source === $candidate.'а') {
                return $candidate;
            }

            if ($this->matchesNominativeCase($shevchenko, $candidate, $source, $field, $grammaticalGender)) {
                return $candidate;
            }
        }

        return null;
    }

    private function matchesNominativeCase(Shevchenko $shevchenko, string $candidate, string $source, string $field, GrammaticalGender $gender): bool
    {
        foreach (['inAccusative', 'inGenitive'] as $method) {
            try {
                $result = $shevchenko->{$method}([
                    $field => $candidate,
                    'gender' => $gender,
                ]);

                if (is_string($result[$field]) && mb_strtolower($result[$field], 'UTF-8') === $source) {
                    return true;
                }
            } catch (\Throwable) {
                // Try the next candidate or grammatical case.
            }
        }

        return false;
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
