<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Services\Names\UkrainianNameNormalizer;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class MonitoringEvaluator
{
    private const string WHITESPACE_PATTERN = '/\s+/u';

    private const string DEGREE_3 = '3 ступеня';

    private const string DEGREE_2 = '2 ступеня';

    private const string DEGREE_1 = '1 ступеня';

    private UkrainianNameNormalizer $normalizer;

    public function __construct(?UkrainianNameNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new UkrainianNameNormalizer;
    }

    /**
     * @param  iterable<MonitoringRecord>  $records
     * @return Collection<int, MonitoringEvaluation>
     */
    public function evaluateAll(iterable $records): Collection
    {
        $awardees = Awardee::query()
            ->with('order')
            ->whereHas('order', function ($builder): void {
                $builder->whereNull('deleted_at');
            })
            ->get();

        $nameIndex = [];

        foreach ($awardees as $index => $awardee) {
            foreach ($this->nameKeys($awardee->full_name) as $key) {
                $nameIndex[$key][$index] = $awardee;
            }
        }

        $evaluations = new Collection;

        foreach ($records as $record) {
            $candidates = [];

            foreach ($this->nameKeys($record->full_name) as $key) {
                foreach ($nameIndex[$key] ?? [] as $index => $awardee) {
                    $candidates[$index] = $awardee;
                }
            }

            ksort($candidates);
            $evaluations->push($this->evaluate($record, $candidates));
        }

        return $evaluations;
    }

    /**
     * @return list<string>
     */
    private function nameKeys(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return [];
        }

        $lower = mb_strtolower($name, 'UTF-8');
        $clean = preg_replace(self::WHITESPACE_PATTERN, ' ', $lower) ?? $lower;
        $normalized = mb_strtolower($this->normalizer->toNominative($name), 'UTF-8');
        $tokens = preg_split(self::WHITESPACE_PATTERN, $normalized) ?: [];
        sort($tokens);

        return ['exact:'.$clean, 'normalized:'.implode(' ', $tokens)];
    }

    /**
     * @param  iterable<Awardee>|null  $awardees
     */
    public function evaluate(MonitoringRecord $record, ?iterable $awardees = null): MonitoringEvaluation
    {
        $awardees ??= Awardee::query()
            ->with('order')
            ->whereHas('order', function ($builder): void {
                $builder->whereNull('deleted_at');
            })
            ->get();

        $matches = [];
        $bestScore = 0;
        $bestMatch = null;

        foreach ($awardees as $awardee) {
            if ($awardee->order === null || $awardee->order->trashed()) {
                continue;
            }

            if (! $this->namesMatch($record->full_name, $awardee->full_name)) {
                continue;
            }

            $rankMatched = $this->ranksMatch($record->rank, $awardee->rank);
            $awardMatched = $this->awardsMatch($record->award, $awardee->award);
            $dateMatched = $this->dateIsWithinSixMonths($awardee->order->published_at, $record->submission_date);

            $matchScore = 1; // Name match (base)

            if ($rankMatched) {
                $matchScore++;
            }

            if ($awardMatched) {
                $matchScore++;
            }

            if ($dateMatched) {
                $matchScore++;
            }

            $match = new MonitoringMatch(
                awardee: $awardee,
                nameMatched: true,
                rankMatched: $rankMatched,
                awardMatched: $awardMatched,
                dateMatched: $dateMatched,
                score: $matchScore,
            );

            $matches[] = $match;

            if ($matchScore > $bestScore) {
                $bestScore = $matchScore;
                $bestMatch = $match;
            }
        }

        return new MonitoringEvaluation(
            record: $record,
            score: $bestScore,
            matches: $matches,
            bestMatch: $bestMatch,
        );
    }

    public function namesMatch(string $monitoredName, string $awardeeName): bool
    {
        $monitoredName = trim($monitoredName);
        $awardeeName = trim($awardeeName);

        if ($monitoredName === '' || $awardeeName === '') {
            return false;
        }

        $lowerMonitored = mb_strtolower($monitoredName, 'UTF-8');
        $lowerAwardee = mb_strtolower($awardeeName, 'UTF-8');

        if ($lowerMonitored === $lowerAwardee) {
            return true;
        }

        $cleanMonitored = preg_replace(self::WHITESPACE_PATTERN, ' ', $lowerMonitored) ?? $lowerMonitored;
        $cleanAwardee = preg_replace(self::WHITESPACE_PATTERN, ' ', $lowerAwardee) ?? $lowerAwardee;

        if ($cleanMonitored === $cleanAwardee) {
            return true;
        }

        $normMonitored = mb_strtolower($this->normalizer->toNominative($monitoredName), 'UTF-8');
        $normAwardee = mb_strtolower($this->normalizer->toNominative($awardeeName), 'UTF-8');

        if ($normMonitored === $normAwardee) {
            return true;
        }

        return $this->tokensMatch($normMonitored, $normAwardee);
    }

    private function tokensMatch(string $normMonitored, string $normAwardee): bool
    {
        $monitoredTokens = preg_split(self::WHITESPACE_PATTERN, $normMonitored) ?: [];
        $awardeeTokens = preg_split(self::WHITESPACE_PATTERN, $normAwardee) ?: [];

        sort($monitoredTokens);
        sort($awardeeTokens);

        return $monitoredTokens !== [] && $monitoredTokens === $awardeeTokens;
    }

    public function ranksMatch(?string $monitoredRank, ?string $awardeeRank): bool
    {
        if ($monitoredRank === null || $awardeeRank === null) {
            return false;
        }

        return trim(mb_strtolower($monitoredRank, 'UTF-8')) === trim(mb_strtolower($awardeeRank, 'UTF-8'));
    }

    public function awardsMatch(?string $monitoredAward, ?string $awardeeAward): bool
    {
        if ($monitoredAward === null || $awardeeAward === null) {
            return false;
        }

        $normMonitored = $this->normalizeAward($monitoredAward);
        $normAwardee = $this->normalizeAward($awardeeAward);

        if ($normMonitored === '' || $normAwardee === '') {
            return false;
        }

        if ($normMonitored === $normAwardee) {
            return true;
        }

        return $this->awardsContainEachOther($normMonitored, $normAwardee);
    }

    private function awardsContainEachOther(string $normMonitored, string $normAwardee): bool
    {
        return str_contains($normAwardee, $normMonitored) || str_contains($normMonitored, $normAwardee);
    }

    public function normalizeAward(string $award): string
    {
        $award = mb_strtolower(trim($award), 'UTF-8');
        $award = str_replace(['«', '»', '"', "'", '“', '”', '„', '`', 'ʼ', '’'], '', $award);

        // Replace Cyrillic 'і' and Latin 'i' variants for degree numerals (III, II, I, 3, 2, 1)
        // Convert Cyrillic 'і' to Latin 'i' in Roman numerals
        $award = preg_replace_callback('/\b[iіIІ]{1,3}\b/u', static function (array $matches): string {
            $len = mb_strlen(str_replace(['і', 'І'], 'i', $matches[0]));

            return (string) $len;
        }, $award) ?? $award;

        // Normalize Roman numerals / degree designations
        $award = str_replace(
            [self::DEGREE_3, self::DEGREE_2, self::DEGREE_1, '3 ст.', '2 ст.', '1 ст.', '3 ст', '2 ст', '1 ст'],
            [self::DEGREE_3, self::DEGREE_2, self::DEGREE_1, self::DEGREE_3, self::DEGREE_2, self::DEGREE_1, self::DEGREE_3, self::DEGREE_2, self::DEGREE_1],
            $award
        );

        // Strip prefix variations
        $award = preg_replace('/^орденом\b/u', 'орден', $award) ?? $award;
        $award = preg_replace('/^медаллю\b/u', 'медаль', $award) ?? $award;
        $award = preg_replace('/^нагрудним\s+знаком\b/u', 'нагрудний знак', $award) ?? $award;
        $award = preg_replace('/^почесною\s+відзнакою\b/u', 'почесна відзнака', $award) ?? $award;
        $award = preg_replace('/^відзнакою\b/u', 'відзнака', $award) ?? $award;

        // Abbreviations
        $award = preg_replace('/\bб\.?\s*хмельницького\b/u', 'богдана хмельницького', $award) ?? $award;

        return trim(preg_replace(self::WHITESPACE_PATTERN, ' ', $award) ?? $award);
    }

    public function dateIsWithinSixMonths(?DateTimeInterface $orderDate, ?DateTimeInterface $submissionDate): bool
    {
        if (! $orderDate instanceof DateTimeInterface || ! $submissionDate instanceof DateTimeInterface) {
            return false;
        }

        $orderCarbon = Carbon::parse($orderDate)->startOfDay();
        $submissionCarbon = Carbon::parse($submissionDate)->startOfDay();

        return $orderCarbon->isAfter($submissionCarbon)
            && $orderCarbon->lessThanOrEqualTo($submissionCarbon->copy()->addMonthsNoOverflow(6));
    }
}
