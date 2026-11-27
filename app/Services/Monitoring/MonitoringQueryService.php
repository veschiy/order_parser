<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringRecord;
use App\Services\PaginationResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class MonitoringQueryService
{
    private const string DECREE_PREFIX = 'Указ #';

    /**
     * @param  list<string>  $filters
     * @return array{query: Builder<MonitoringRecord>, scoreFilter: int[]|null, matchesFilter: array{decreeDisplays: list<string>, hasNoMatches: bool}}
     */
    public function buildFilteredQuery(string $search, array $filters = [], ?string $sort = null, string $direction = 'asc'): array
    {
        $query = MonitoringRecord::query();

        if ($search !== '') {
            $pattern = '%'.mb_strtolower($search, 'UTF-8').'%';
            $query->where(function ($builder) use ($pattern): void {
                $builder
                    ->whereRaw('mb_lower(full_name) like ?', [$pattern])
                    ->orWhereRaw("mb_lower(coalesce(tax_id, '')) like ?", [$pattern])
                    ->orWhereRaw("mb_lower(coalesce(award, '')) like ?", [$pattern])
                    ->orWhereRaw("mb_lower(coalesce(sheet, '')) like ?", [$pattern]);
            });
        }

        $scoreFilter = null;
        $matchesFilter = ['decreeDisplays' => [], 'hasNoMatches' => false];

        foreach ($filters as $filter) {
            $parts = explode(':', $filter, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $column = $parts[0];
            $values = $parts[1] === '' ? [] : explode(',', $parts[1]);

            if ($column === 'score') {
                [$query, $scoreFilter] = $this->applyScoreFilter($query, $values);

                continue;
            }

            if ($column === 'matches') {
                $matchesFilter['hasNoMatches'] = in_array('__empty__', $values, true);
                $matchesFilter['decreeDisplays'] = array_values(array_filter($values, fn (string $value): bool => $value !== '__empty__'));

                continue;
            }

            $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];
            if (in_array($column, $allowedColumns, true)) {
                $this->applyColumnFilter($query, $column, $values);
            }
        }

        $this->applySorting($query, $sort, $direction);

        return ['query' => $query, 'scoreFilter' => $scoreFilter, 'matchesFilter' => $matchesFilter];
    }

    /**
     * @param  list<string>  $filters
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>, totalCount: int, distinctMatches: list<string>}
     */
    public function indexData(string $search, array $filters, ?string $sort, string $direction, int $page, int $perPage, MonitoringEvaluationCache $evaluationCache): array
    {
        ['query' => $query, 'scoreFilter' => $scoreFilter, 'matchesFilter' => $matchesFilter] = $this->buildFilteredQuery($search, $filters, $sort, $direction);

        $hasMatchesFilter = ! empty($matchesFilter['decreeDisplays']) || $matchesFilter['hasNoMatches'];

        // Collect distinct decree display numbers from all evaluations before pagination.
        $allRecords = $query->get();
        $allEvaluations = $evaluationCache->forRecords($allRecords);
        $decreeDisplays = $this->buildDecreeDisplays($allEvaluations);

        $evaluations = $this->applyFilters($allEvaluations, $scoreFilter, $hasMatchesFilter, $matchesFilter);
        $evaluations = $this->applyComputedSorting($evaluations, $sort, $direction);

        $total = $evaluations->count();
        $evaluations = $evaluations->forPage($page, $perPage);

        return $this->buildJsonResponse($evaluations, $page, $total, $perPage, $decreeDisplays);
    }

    /**
     * Return distinct values for a filter dropdown.
     *
     * @return list<string>
     */
    public function distinctValues(?string $column, string $search, MonitoringEvaluationCache $evaluationCache): array
    {
        $formatValue = static function (mixed $value): string {
            if ($value instanceof Carbon) {
                return $value->format('Y-m-d');
            }

            if (is_string($value) || is_int($value)) {
                return (string) $value;
            }

            return '';
        };

        if ($column === 'score') {
            return ['0', '1', '2', '3', '4', '__empty__'];
        }

        if ($column === 'matches') {
            ['query' => $matchQuery] = $this->buildFilteredQuery($search);
            $evaluations = $evaluationCache->forRecords($matchQuery->get());

            return $this->buildDecreeDisplays($evaluations);
        }

        $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];
        if (! in_array($column, $allowedColumns, true)) {
            return [];
        }

        ['query' => $query] = $this->buildFilteredQuery($search);
        /** @var list<string> $values */
        $values = $query->distinct()->pluck($column)->filter()
            ->map($formatValue)
            ->sort()->values()->toArray();
        $values[] = '__empty__';

        return $values;
    }

    /**
     * @param  Builder<MonitoringRecord>  $query
     * @param  list<string>  $values
     * @return array{Builder<MonitoringRecord>, int[]|null}
     */
    private function applyScoreFilter(Builder $query, array $values): array
    {
        if ($values === [] || $values === ['']) {
            $query->whereRaw('1 = 0');

            return [$query, null];
        }

        return [$query, array_map(intval(...), $values)];
    }

    /**
     * Apply score and matches filters to evaluations.
     *
     * @param  Collection<int, MonitoringEvaluation>  $evaluations
     * @param  int[]|null  $scoreFilter
     * @param  array{decreeDisplays: list<string>, hasNoMatches: bool}  $matchesFilter
     * @return Collection<int, MonitoringEvaluation>
     */
    private function applyFilters(Collection $evaluations, ?array $scoreFilter, bool $hasMatchesFilter, array $matchesFilter): Collection
    {
        if ($scoreFilter === null && ! $hasMatchesFilter) {
            return $evaluations;
        }

        if ($scoreFilter !== null) {
            $evaluations = $evaluations->filter(fn ($evaluation): bool => in_array($evaluation->score, $scoreFilter, true));
        }

        if ($hasMatchesFilter) {
            $evaluations = $evaluations->filter(function ($evaluation) use ($matchesFilter): bool {
                $evaluationDecreeDisplays = array_map(
                    $this->decreeDisplay(...),
                    $evaluation->matches,
                );

                return $this->passesMatchesFilter($evaluationDecreeDisplays !== [], $evaluationDecreeDisplays, $matchesFilter);
            });
        }

        return $evaluations;
    }

    /**
     * Apply a column filter, handling the '__empty__' sentinel for null/empty values.
     *
     * @param  Builder<MonitoringRecord>  $query
     * @param  list<string>  $values
     */
    private function applyColumnFilter(Builder $query, string $column, array $values): void
    {
        $hasEmpty = in_array('__empty__', $values, true);
        $nonEmptyValues = array_values(array_filter($values, fn (string $value): bool => $value !== '__empty__'));

        if ($hasEmpty && $nonEmptyValues === []) {
            $query->where(function ($builder) use ($column): void {
                $builder->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $query->where(function (Builder $builder) use ($column, $nonEmptyValues, $hasEmpty): void {
            $builder->whereIn($column, $nonEmptyValues);
            if ($hasEmpty) {
                $builder->orWhere(function (Builder $inner) use ($column): void {
                    $inner->whereNull($column)->orWhere($column, '');
                });
            }
        });
    }

    /**
     * Apply post-evaluation sorting for computed columns (score, matches).
     *
     * @param  Collection<int, MonitoringEvaluation>  $evaluations
     * @return Collection<int, MonitoringEvaluation>
     */
    private function applyComputedSorting(Collection $evaluations, ?string $sort, string $direction): Collection
    {
        if ($sort === 'score') {
            $evaluations = $evaluations->sortBy(fn ($evaluation) => $evaluation->score, SORT_NUMERIC, $direction === 'desc');
        }

        if ($sort === 'matches') {
            $evaluations = $evaluations->sortBy(fn ($evaluation): int => count($evaluation->matches), SORT_NUMERIC, $direction === 'desc');
        }

        return $evaluations;
    }

    /**
     * Build a sorted array of distinct decree display numbers from evaluations.
     *
     * @param  Collection<int, MonitoringEvaluation>  $evaluations
     * @return list<string>
     */
    private function buildDecreeDisplays(Collection $evaluations): array
    {
        $decreeDisplays = [];
        foreach ($evaluations as $evaluation) {
            foreach ($evaluation->matches as $match) {
                $decreeDisplays[] = $this->decreeDisplay($match);
            }
        }

        $decreeDisplays = array_values(array_unique($decreeDisplays, SORT_STRING));
        sort($decreeDisplays, SORT_NATURAL | SORT_FLAG_CASE);
        $decreeDisplays[] = '__empty__';

        /** @var list<string> $decreeDisplays */
        return $decreeDisplays;
    }

    /**
     * @param  list<string>  $evaluationDecreeDisplays
     * @param  array{decreeDisplays: list<string>, hasNoMatches: bool}  $matchesFilter
     */
    private function passesMatchesFilter(bool $hasMatches, array $evaluationDecreeDisplays, array $matchesFilter): bool
    {
        $hasDecreeFilter = $matchesFilter['decreeDisplays'] !== [];
        $hasDecreeMatch = $hasMatches && count(array_intersect($evaluationDecreeDisplays, $matchesFilter['decreeDisplays'])) > 0;

        if ($matchesFilter['hasNoMatches']) {
            return $hasDecreeFilter ? ! $hasMatches || $hasDecreeMatch : ! $hasMatches;
        }

        return ! $hasDecreeFilter || $hasDecreeMatch;
    }

    /**
     * Apply sorting to the query builder.
     *
     * @param  Builder<MonitoringRecord>  $query
     */
    private function applySorting(Builder $query, ?string $sort, string $direction): void
    {
        $sortableColumns = ['sheet', 'full_name', 'submission_date', 'award'];

        if ($sort !== null && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');

            return;
        }

        $query->orderBy('id');
    }

    /**
     * Build the JSON response array for the monitoring table.
     *
     * @param  Collection<int, MonitoringEvaluation>  $evaluations
     * @param  list<string>  $decreeDisplays
     * @return array{data: list<array<string, mixed>>, pagination: array<string, mixed>, totalCount: int, distinctMatches: list<string>}
     */
    private function buildJsonResponse(Collection $evaluations, int $page, int $total, int $perPage, array $decreeDisplays): array
    {
        /** @var list<array<string, mixed>> $data */
        $data = $evaluations->map(fn ($evaluation): array => $this->evaluationToArray($evaluation))->values()->toArray();

        return [
            'data' => $data,
            'pagination' => PaginationResponse::fromValues($page, $total, $perPage),
            'totalCount' => MonitoringRecord::query()->count(),
            'distinctMatches' => $decreeDisplays,
        ];
    }

    /**
     * Convert a single evaluation to an array for the JSON response.
     *
     * @return array<string, mixed>
     */
    private function evaluationToArray(MonitoringEvaluation $evaluation): array
    {
        $matchesSummary = $evaluation->matches === []
            ? 'Не знайдено'
            : implode(', ', array_map($this->decreeDisplay(...), $evaluation->matches));

        return [
            'id' => $evaluation->record->id,
            'sheet' => $evaluation->record->sheet ?? '',
            'full_name' => $evaluation->record->full_name,
            'submission_date' => $evaluation->record->submission_date?->format('Y-m-d') ?? '',
            'submission_date_display' => $evaluation->record->submission_date?->format('d.m.Y') ?? '—',
            'award' => $evaluation->record->award ?? '',
            'rank' => $evaluation->record->rank ?? '',
            'score' => $evaluation->score,
            'matches' => array_map($this->matchToArray(...), $evaluation->matches),
            'matchesSummary' => $matchesSummary,
        ];
    }

    /**
     * Convert a single match to an array for the JSON response.
     *
     * @return array<string, mixed>
     */
    private function matchToArray(MonitoringMatch $match): array
    {
        return [
            'awardee_id' => $match->awardee->id,
            'order_id' => $match->awardee->order_id,
            'order_display' => $this->decreeDisplay($match),
            'award' => $match->awardee->award ?? '—',
            'score' => $match->score,
            'rankMatched' => $match->rankMatched,
            'awardMatched' => $match->awardMatched,
            'dateMatched' => $match->dateMatched,
        ];
    }

    private function decreeDisplay(MonitoringMatch $match): string
    {
        return $match->awardee->order?->displayNumber()
            ?? self::DECREE_PREFIX.$match->awardee->order_id;
    }
}
