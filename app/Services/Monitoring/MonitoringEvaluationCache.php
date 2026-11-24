<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Awardee;
use App\Models\MonitoringRecord;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @phpstan-type CachedAwardee array{id: int, order_id: int, award: string|null}
 * @phpstan-type CachedOrder array{id: int, number: string|null, published_at: string|null}
 * @phpstan-type CachedMatch array{awardee: CachedAwardee, order: CachedOrder|null, nameMatched: bool, rankMatched: bool, awardMatched: bool, dateMatched: bool, score: int}
 * @phpstan-type CachedEvaluation array{score: int, matches: list<CachedMatch>, bestMatchIndex: int|null}
 */
final class MonitoringEvaluationCache
{
    private const string VERSION_KEY = 'monitoring.evaluations.version';

    private const string SNAPSHOT_KEY_PREFIX = 'monitoring.evaluations.snapshot.';

    private const int CACHE_SECONDS = 3600;

    public function __construct(private readonly MonitoringEvaluator $evaluator) {}

    /**
     * @param  iterable<MonitoringRecord>  $records
     * @return Collection<int, MonitoringEvaluation>
     */
    public function forRecords(iterable $records): Collection
    {
        $snapshot = $this->snapshot();
        $evaluations = new Collection;

        foreach ($records as $record) {
            $evaluationData = $snapshot[$record->id] ?? null;

            if (! is_array($evaluationData)) {
                continue;
            }

            $evaluations->push($this->restoreEvaluation($record, $evaluationData));
        }

        return $evaluations;
    }

    public function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, Str::uuid()->toString());
    }

    /**
     * @return array<int, CachedEvaluation>
     */
    private function snapshot(): array
    {
        $version = Cache::rememberForever(self::VERSION_KEY, static fn (): string => Str::uuid()->toString());
        if (! is_string($version)) {
            $version = Str::uuid()->toString();
            Cache::forever(self::VERSION_KEY, $version);
        }

        $cacheKey = self::SNAPSHOT_KEY_PREFIX.$version;

        $snapshot = Cache::remember($cacheKey, self::CACHE_SECONDS, function (): array {
            $records = MonitoringRecord::query()->get();
            $snapshot = [];

            foreach ($this->evaluator->evaluateAll($records) as $evaluation) {
                $snapshot[$evaluation->record->id] = $this->serializeEvaluation($evaluation);
            }

            return $snapshot;
        });

        return $this->normalizeSnapshot($snapshot);
    }

    /**
     * @return array<int, CachedEvaluation>
     */
    private function normalizeSnapshot(mixed $snapshot): array
    {
        if (! is_array($snapshot)) {
            return [];
        }

        $evaluations = [];

        foreach ($snapshot as $recordId => $evaluationData) {
            if (is_int($recordId) && $this->isCachedEvaluation($evaluationData)) {
                $evaluations[$recordId] = $evaluationData;
            }
        }

        return $evaluations;
    }

    /**
     * @phpstan-assert-if-true CachedEvaluation $evaluationData
     */
    private function isCachedEvaluation(mixed $evaluationData): bool
    {
        if (! is_array($evaluationData)
            || ! array_key_exists('score', $evaluationData)
            || ! is_int($evaluationData['score'])
            || ! array_key_exists('matches', $evaluationData)
            || ! is_array($evaluationData['matches'])
            || ! array_is_list($evaluationData['matches'])
            || ! array_key_exists('bestMatchIndex', $evaluationData)
            || ($evaluationData['bestMatchIndex'] !== null && ! is_int($evaluationData['bestMatchIndex']))) {
            return false;
        }

        foreach ($evaluationData['matches'] as $matchData) {
            if (! $this->isCachedMatch($matchData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true CachedMatch $matchData
     */
    private function isCachedMatch(mixed $matchData): bool
    {
        if (! is_array($matchData)
            || ! array_key_exists('awardee', $matchData)
            || ! $this->isCachedAwardee($matchData['awardee'])
            || ! array_key_exists('order', $matchData)
            || ($matchData['order'] !== null && ! $this->isCachedOrder($matchData['order']))
            || ! is_bool($matchData['nameMatched'] ?? null)
            || ! is_bool($matchData['rankMatched'] ?? null)
            || ! is_bool($matchData['awardMatched'] ?? null)
            || ! is_bool($matchData['dateMatched'] ?? null)
            || ! is_int($matchData['score'] ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true CachedAwardee $awardeeData
     */
    private function isCachedAwardee(mixed $awardeeData): bool
    {
        return is_array($awardeeData)
            && is_int($awardeeData['id'] ?? null)
            && is_int($awardeeData['order_id'] ?? null)
            && array_key_exists('award', $awardeeData)
            && ($awardeeData['award'] === null || is_string($awardeeData['award']));
    }

    /**
     * @phpstan-assert-if-true CachedOrder $orderData
     */
    private function isCachedOrder(mixed $orderData): bool
    {
        return is_array($orderData)
            && is_int($orderData['id'] ?? null)
            && array_key_exists('number', $orderData)
            && ($orderData['number'] === null || is_string($orderData['number']))
            && array_key_exists('published_at', $orderData)
            && ($orderData['published_at'] === null || is_string($orderData['published_at']));
    }

    /**
     * @return CachedEvaluation
     */
    private function serializeEvaluation(MonitoringEvaluation $evaluation): array
    {
        $matches = array_map(function (MonitoringMatch $match): array {
            $order = $match->awardee->order;
            $publishedAt = $order?->getRawOriginal('published_at');

            return [
                'awardee' => [
                    'id' => $match->awardee->id,
                    'order_id' => $match->awardee->order_id,
                    'award' => $match->awardee->award,
                ],
                'order' => $order === null ? null : [
                    'id' => $order->id,
                    'number' => $order->number,
                    'published_at' => is_string($publishedAt) ? $publishedAt : null,
                ],
                'nameMatched' => $match->nameMatched,
                'rankMatched' => $match->rankMatched,
                'awardMatched' => $match->awardMatched,
                'dateMatched' => $match->dateMatched,
                'score' => $match->score,
            ];
        }, $evaluation->matches);

        $bestMatchIndex = null;
        foreach ($evaluation->matches as $index => $match) {
            if ($match === $evaluation->bestMatch) {
                $bestMatchIndex = $index;

                break;
            }
        }

        return [
            'score' => $evaluation->score,
            'matches' => $matches,
            'bestMatchIndex' => $bestMatchIndex,
        ];
    }

    /**
     * @param  CachedEvaluation  $evaluationData
     */
    private function restoreEvaluation(MonitoringRecord $record, array $evaluationData): MonitoringEvaluation
    {
        $matches = [];

        foreach ($evaluationData['matches'] as $matchData) {
            $awardee = (new Awardee)->newFromBuilder($matchData['awardee']);
            $order = $matchData['order'] === null
                ? null
                : (new Order)->newFromBuilder($matchData['order']);

            $awardee->setRelation('order', $order);
            $matches[] = new MonitoringMatch(
                awardee: $awardee,
                nameMatched: $matchData['nameMatched'],
                rankMatched: $matchData['rankMatched'],
                awardMatched: $matchData['awardMatched'],
                dateMatched: $matchData['dateMatched'],
                score: $matchData['score'],
            );
        }

        $bestMatch = $evaluationData['bestMatchIndex'] === null
            ? null
            : ($matches[$evaluationData['bestMatchIndex']] ?? null);

        return new MonitoringEvaluation(
            record: $record,
            score: $evaluationData['score'],
            matches: $matches,
            bestMatch: $bestMatch,
        );
    }
}
