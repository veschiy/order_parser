<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MonitoringRecord;
use App\Services\Monitoring\MonitoringCsvImporter;
use App\Services\Monitoring\MonitoringEvaluation;
use App\Services\Monitoring\MonitoringEvaluator;
use App\Services\Monitoring\MonitoringMatch;
use App\Services\PaginationResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MonitoringController extends Controller
{
    private const string DECREE_PREFIX = 'Указ #';

    public function index(Request $request, MonitoringEvaluator $evaluator): View
    {
        $search = trim($request->string('q')->toString());

        ['query' => $query] = $this->buildFilteredQuery($search);
        $records = $query->paginate(15);
        $evaluations = $evaluator->evaluateAll($records->items());

        return view('monitoring.index', [
            'evaluations' => $evaluations,
            'paginator' => $records,
            'search' => $search,
            'totalCount' => MonitoringRecord::query()->count(),
        ]);
    }

    /**
     * @param  string[]  $filters
     * @return array{query: Builder<MonitoringRecord>, scoreFilter: int[]|null, matchesFilter: array{decreeDisplays: list<string>, hasNoMatches: bool}}
     */
    private function buildFilteredQuery(string $search, array $filters = [], ?string $sort = null, string $direction = 'asc'): array
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

        // Apply column filters (score and matches are computed, handled separately)
        $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];
        $scoreFilter = null;
        $matchesFilter = ['decreeDisplays' => [], 'hasNoMatches' => false];

        foreach ($filters as $filter) {
            $parts = explode(':', $filter, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $column = $parts[0];
            $rawValues = $parts[1];

            // Empty values means all checkboxes unchecked — return no results
            if ($rawValues === '') {
                $query->whereRaw('1 = 0');

                continue;
            }

            $values = explode(',', $rawValues);

            if ($column === 'score') {
                // Empty score filter means all unchecked — return no results
                if ($values === ['']) {
                    $query->whereRaw('1 = 0');
                } else {
                    $scoreFilter = array_map(intval(...), $values);
                }

                continue;
            }

            if ($column === 'matches') {
                // Matches filter: '__empty__' = no matches, other values are decree display strings
                $matchesFilter['hasNoMatches'] = in_array('__empty__', $values, true);
                $matchesFilter['decreeDisplays'] = array_values(array_filter($values, fn (string $v): bool => $v !== '__empty__'));

                continue;
            }

            if (in_array($column, $allowedColumns, true)) {
                $this->applyColumnFilter($query, $column, $values);
            }
        }

        $this->applySorting($query, $sort, $direction);

        return ['query' => $query, 'scoreFilter' => $scoreFilter, 'matchesFilter' => $matchesFilter];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sheet' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'submission_date' => ['nullable', 'date'],
            'rank' => ['nullable', 'string', 'max:255'],
            'award' => ['nullable', 'string', 'max:255'],
        ]);

        $fullName = is_string($validated['full_name']) ? trim($validated['full_name']) : '';

        MonitoringRecord::query()->create([
            'sheet' => $validated['sheet'] ?? null,
            'full_name' => $fullName,
            'tax_id' => $validated['tax_id'] ?? null,
            'submission_date' => $validated['submission_date'] ?? null,
            'rank' => is_string($validated['rank'] ?? null) ? trim($validated['rank']) : null,
            'award' => $validated['award'] ?? null,
        ]);

        return redirect()->route('monitoring.index')->with('status', 'Запис моніторингу успішно додано.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = MonitoringRecord::query()->findOrFail($id);

        $validated = $request->validate([
            'sheet' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'submission_date' => ['nullable', 'date'],
            'rank' => ['nullable', 'string', 'max:255'],
            'award' => ['nullable', 'string', 'max:255'],
        ]);

        $fullName = is_string($validated['full_name']) ? trim($validated['full_name']) : '';

        $record->update([
            'sheet' => $validated['sheet'] ?? null,
            'full_name' => $fullName,
            'tax_id' => $validated['tax_id'] ?? null,
            'submission_date' => $validated['submission_date'] ?? null,
            'rank' => is_string($validated['rank'] ?? null) ? trim($validated['rank']) : null,
            'award' => $validated['award'] ?? null,
        ]);

        return redirect()->route('monitoring.index')->with('status', 'Запис моніторингу оновлено.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $record = MonitoringRecord::query()->findOrFail($id);
        $record->delete();

        return redirect()->route('monitoring.index')->with('status', 'Запис моніторингу видалено.');
    }

    public function clear(): RedirectResponse
    {
        MonitoringRecord::query()->delete();

        return redirect()->route('monitoring.index')->with('status', 'Усі записи моніторингу видалено.');
    }

    public function import(Request $request, MonitoringCsvImporter $importer): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('csv_file');

        if (! $file instanceof UploadedFile) {
            return redirect()->route('monitoring.index')->withErrors(['csv_file' => 'Файл не завантажено.']);
        }

        $imported = $importer->import($file);

        return redirect()->route('monitoring.index')->with('status', "Успішно імпортовано {$imported} записів у таблицю моніторингу.");
    }

    public function export(Request $request, MonitoringEvaluator $evaluator): StreamedResponse
    {
        ['query' => $query] = $this->buildFilteredQuery(trim($request->string('q')->toString()));

        if ($request->isMethod('post')) {
            $validated = $request->validate(['record_ids' => ['required', 'string', 'json']]);
            $rawIds = is_string($validated['record_ids']) ? $validated['record_ids'] : '[]';
            $ids = json_decode($rawIds, true);
            abort_unless(is_array($ids) && array_is_list($ids) && count(array_filter($ids, fn ($id): bool => ! is_int($id) || $id < 1)) === 0, 422);
            $query->whereKey($ids);
        }

        $records = $query->get();
        $evaluations = $evaluator->evaluateAll($records);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="monitoring_export.csv"',
        ];

        return response()->stream(function () use ($evaluations): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // Write UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Аркуш',
                'ПІБ',
                'Звання',
                'Дата подання',
                'Нагорода',
                'Бал',
                'Знайдено в указах',
            ], ';', '"', '');

            foreach ($evaluations as $evaluation) {
                $matchedDecrees = [];
                foreach ($evaluation->matches as $match) {
                    $matchedDecrees[] = $this->decreeDisplay($match).' (бал: '.$match->score.')';
                }

                fputcsv($handle, [
                    $evaluation->record->sheet ?? '',
                    $evaluation->record->full_name,
                    $evaluation->record->rank ?? '',
                    $evaluation->record->submission_date?->format('d.m.Y') ?? '',
                    $evaluation->record->award ?? '',
                    $evaluation->score,
                    implode('; ', $matchedDecrees),
                ], ';', '"', '');
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function indexJson(Request $request, MonitoringEvaluator $evaluator): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        $perPage = $request->integer('perPage', 15);
        $page = max(1, $request->integer('page', 1));
        $sort = $request->string('sort')->toString() ?: null;
        $direction = $request->string('direction')->toString() ?: 'asc';
        $filtersRaw = $request->input('filters', []);
        /** @var array<string> $filters */
        $filters = is_array($filtersRaw) ? array_filter($filtersRaw, is_string(...)) : [];

        ['query' => $query, 'scoreFilter' => $scoreFilter, 'matchesFilter' => $matchesFilter] = $this->buildFilteredQuery($search, $filters, $sort, $direction);

        $hasMatchesFilter = ! empty($matchesFilter['decreeDisplays']) || $matchesFilter['hasNoMatches'];

        // Collect distinct decree display numbers from ALL evaluations (before pagination) for the filter dropdown
        $allRecords = $query->get();
        $allEvaluations = $evaluator->evaluateAll($allRecords);
        $decreeDisplays = $this->buildDecreeDisplays($allEvaluations);

        if ($scoreFilter !== null || $hasMatchesFilter) {
            // Score or matches are computed, not stored — must evaluate all records first, then filter and paginate
            $evaluations = $allEvaluations;

            // Apply score filter
            if ($scoreFilter !== null) {
                $evaluations = $evaluations->filter(fn ($evaluation): bool => in_array($evaluation->score, $scoreFilter, true));
            }

            // Apply matches filter (filter by specific decree displays and/or no matches)
            if ($hasMatchesFilter) {
                $evaluations = $evaluations->filter(function ($evaluation) use ($matchesFilter): bool {
                    $evalDecreeDisplays = array_map(
                        static fn (MonitoringMatch $m) => $m->awardee->order ? $m->awardee->order->displayNumber() : self::DECREE_PREFIX.$m->awardee->order_id,
                        $evaluation->matches
                    );
                    $hasMatches = count($evalDecreeDisplays) > 0;

                    return $this->passesMatchesFilter($hasMatches, $evalDecreeDisplays, $matchesFilter);
                });
            }

            $evaluations = $this->applyComputedSorting($evaluations, $sort, $direction);

            $total = $evaluations->count();
            $evaluations = $evaluations->forPage($page, $perPage);
        } else {
            // No score/matches filter — sort first, then paginate
            $total = $allRecords->count();
            $evaluations = $allEvaluations;

            $evaluations = $this->applyComputedSorting($evaluations, $sort, $direction);

            $evaluations = $evaluations->forPage($page, $perPage);
        }

        return response()->json($this->buildJsonResponse($evaluations, $page, $total, $perPage, $decreeDisplays));
    }

    /**
     * Apply a column filter, handling the '__empty__' sentinel for null/empty values.
     *
     * @param  Builder<MonitoringRecord>  $query
     * @param  array<string>  $values
     */
    private function applyColumnFilter(Builder $query, string $column, array $values): void
    {
        $hasEmpty = in_array('__empty__', $values, true);
        $nonEmptyValues = array_values(array_filter($values, fn (string $v): bool => $v !== '__empty__'));

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
     * Get the display string for a decree reference.
     */
    private function decreeDisplay(MonitoringMatch $match): string
    {
        return $match->awardee->order
            ? $match->awardee->order->displayNumber()
            : self::DECREE_PREFIX.$match->awardee->order_id;
    }

    /**
     * Check if an evaluation passes the matches filter criteria.
     *
     * @param  array<string>  $evalDecreeDisplays
     * @param  array{decreeDisplays: list<string>, hasNoMatches: bool}  $matchesFilter
     */
    private function passesMatchesFilter(bool $hasMatches, array $evalDecreeDisplays, array $matchesFilter): bool
    {
        $hasDecreeFilter = ! empty($matchesFilter['decreeDisplays']);
        $hasNoMatchFilter = $matchesFilter['hasNoMatches'];
        $hasDecreeMatch = $hasMatches && count(array_intersect($evalDecreeDisplays, $matchesFilter['decreeDisplays'])) > 0;

        if ($hasNoMatchFilter && $hasDecreeFilter) {
            return ! $hasMatches || $hasDecreeMatch;
        }

        if ($hasNoMatchFilter) {
            return ! $hasMatches;
        }

        if ($hasDecreeFilter) {
            return $hasDecreeMatch;
        }

        return true;
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
     * Build the JSON response array for indexJson.
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
     * Convert a single evaluation to an array for JSON response.
     *
     * @return array<string, mixed>
     */
    private function evaluationToArray(MonitoringEvaluation $evaluation): array
    {
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
            'matchesSummary' => $this->matchesSummary($evaluation->matches),
        ];
    }

    /**
     * Convert a single match to an array for JSON response.
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

    /**
     * Build a summary string from matches.
     *
     * @param  list<MonitoringMatch>  $matches
     */
    private function matchesSummary(array $matches): string
    {
        if ($matches === []) {
            return 'Не знайдено';
        }

        return implode(', ', array_map($this->decreeDisplay(...), $matches));
    }

    /**
     * Return distinct values for a given column, used by the filter dropdown UI.
     */
    public function distinctJson(Request $request): JsonResponse
    {
        $column = $request->has('column') ? ($request->string('column')->toString() ?: null) : null;
        $search = trim($request->string('q')->toString());

        $values = $this->resolveDistinctValues($column, $search);

        return response()->json(['values' => $values]);
    }

    /**
     * @return list<string>
     */
    private function resolveDistinctValues(?string $column, string $search): array
    {
        if ($column === 'score') {
            return ['0', '1', '2', '3', '4', '__empty__'];
        }

        if ($column === 'matches') {
            ['query' => $matchQuery] = $this->buildFilteredQuery($search);
            $records = $matchQuery->get();
            $evaluations = (new MonitoringEvaluator)->evaluateAll($records);

            return $this->buildDecreeDisplays($evaluations);
        }

        $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];

        if (! in_array($column, $allowedColumns, true)) {
            return [];
        }

        ['query' => $query] = $this->buildFilteredQuery($search);
        /** @var list<string> $values */
        $values = $query->distinct()->pluck($column)->filter()->map(fn ($v): string => $this->formatDistinctValue($v))->sort()->values()->toArray();
        $values[] = '__empty__';

        return $values;
    }

    private function formatDistinctValue(mixed $v): string
    {
        if ($v instanceof Carbon) {
            return $v->format('Y-m-d');
        }

        if (is_string($v)) {
            return $v;
        }

        if (is_int($v)) {
            return (string) $v;
        }

        return '';
    }
}
