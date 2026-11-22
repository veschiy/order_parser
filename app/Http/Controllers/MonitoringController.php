<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MonitoringRecord;
use App\Services\Monitoring\MonitoringCsvImporter;
use App\Services\Monitoring\MonitoringEvaluator;
use App\Services\Monitoring\MonitoringMatch;
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

        // Apply sorting (score is computed post-evaluation, so we only sort DB columns here)
        $sortableColumns = ['sheet', 'full_name', 'submission_date', 'award'];
        if ($sort !== null && in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        } elseif ($sort !== null && $sort === 'score') {
            // Score sorting is handled post-evaluation in indexJson.
            // Use a stable default order here so the post-evaluation sort is deterministic.
            $query->orderBy('id');
        } else {
            $query->orderBy('id');
        }

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
                    $order = $match->awardee->order;
                    $matchedDecrees[] = ($order ? $order->displayNumber() : 'Указ #'.$match->awardee->order_id).' (бал: '.$match->score.')';
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

        if ($scoreFilter !== null || $hasMatchesFilter) {
            // Score or matches are computed, not stored — must evaluate all records first, then filter and paginate
            $records = $query->get();
            $evaluations = $evaluator->evaluateAll($records);

            // Apply score filter
            if ($scoreFilter !== null) {
                $evaluations = $evaluations->filter(fn ($evaluation): bool => in_array($evaluation->score, $scoreFilter, true));
            }

            // Apply matches filter (filter by specific decree displays and/or no matches)
            if ($hasMatchesFilter) {
                $evaluations = $evaluations->filter(function ($evaluation) use ($matchesFilter): bool {
                    $decreeDisplays = array_map(static fn (MonitoringMatch $m) => ($m->awardee->order ? $m->awardee->order->displayNumber() : 'Указ #'.$m->awardee->order_id), $evaluation->matches);
                    $hasMatches = count($decreeDisplays) > 0;

                    // If only "no matches" is selected
                    if ($matchesFilter['hasNoMatches'] && $matchesFilter['decreeDisplays'] === []) {
                        return ! $hasMatches;
                    }

                    // If only specific decrees are selected
                    if (! $matchesFilter['hasNoMatches'] && $matchesFilter['decreeDisplays'] !== []) {
                        return $hasMatches && count(array_intersect($decreeDisplays, $matchesFilter['decreeDisplays'])) > 0;
                    }

                    // Both specific decrees and "no matches" selected
                    if ($matchesFilter['hasNoMatches'] && $matchesFilter['decreeDisplays'] !== []) {
                        return ! $hasMatches || count(array_intersect($decreeDisplays, $matchesFilter['decreeDisplays'])) > 0;
                    }

                    return true;
                });
            }

            // Apply score sorting post-evaluation
            if ($sort === 'score') {
                $evaluations = $evaluations->sortBy(fn ($evaluation) => $evaluation->score, SORT_NUMERIC, $direction === 'desc');
            }

            // Apply matches sorting post-evaluation
            if ($sort === 'matches') {
                $evaluations = $evaluations->sortBy(fn ($evaluation) => count($evaluation->matches), SORT_NUMERIC, $direction === 'desc');
            }

            $total = $evaluations->count();
            $evaluations = $evaluations->forPage($page, $perPage);
        } else {
            // No score/matches filter — paginate at the DB level for efficiency
            $total = $query->count();
            $records = $query->forPage($page, $perPage)->get();
            /** @var Collection<int, MonitoringRecord> $records */
            $evaluations = $evaluator->evaluateAll($records);

            // Apply score sorting post-evaluation (score is computed, not a DB column)
            if ($sort === 'score') {
                $evaluations = $evaluations->sortBy(fn ($evaluation) => $evaluation->score, SORT_NUMERIC, $direction === 'desc');
            }

            // Apply matches sorting post-evaluation
            if ($sort === 'matches') {
                $evaluations = $evaluations->sortBy(fn ($evaluation) => count($evaluation->matches), SORT_NUMERIC, $direction === 'desc');
            }
        }

        return response()->json([
            'data' => $evaluations->map(fn ($evaluation): array => [
                'id' => $evaluation->record->id,
                'sheet' => $evaluation->record->sheet ?? '',
                'full_name' => $evaluation->record->full_name,
                'submission_date' => $evaluation->record->submission_date?->format('Y-m-d') ?? '',
                'submission_date_display' => $evaluation->record->submission_date?->format('d.m.Y') ?? '—',
                'award' => $evaluation->record->award ?? '',
                'rank' => $evaluation->record->rank ?? '',
                'score' => $evaluation->score,
                'matches' => array_map(static fn (MonitoringMatch $m): array => [
                    'awardee_id' => $m->awardee->id,
                    'order_id' => $m->awardee->order_id,
                    'order_display' => $m->awardee->order ? $m->awardee->order->displayNumber() : 'Указ #'.$m->awardee->order_id,
                    'award' => $m->awardee->award ?? '—',
                    'score' => $m->score,
                    'rankMatched' => $m->rankMatched,
                    'awardMatched' => $m->awardMatched,
                    'dateMatched' => $m->dateMatched,
                ], $evaluation->matches),
                'matchesSummary' => $evaluation->matches === []
                    ? 'Не знайдено'
                    : implode(', ', array_map(static fn (MonitoringMatch $m) => ($m->awardee->order ? $m->awardee->order->displayNumber() : 'Указ #'.$m->awardee->order_id), $evaluation->matches)),
            ])->values(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
            'totalCount' => MonitoringRecord::query()->count(),
        ]);
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
            // Only empty filter — match null or empty string
            $query->where(function ($builder) use ($column): void {
                $builder->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($hasEmpty) {
            // Both empty and non-empty values
            $query->where(function (Builder $builder) use ($column, $nonEmptyValues): void {
                $builder->whereIn($column, $nonEmptyValues)->orWhere(function (Builder $inner) use ($column): void {
                    $inner->whereNull($column)->orWhere($column, '');
                });
            });

            return;
        }

        // Only non-empty values
        $query->whereIn($column, $nonEmptyValues);
    }

    /**
     * Return distinct values for a given column, used by the filter dropdown UI.
     */
    public function distinctJson(Request $request): JsonResponse
    {
        $column = $request->input('column');
        $search = trim($request->string('q')->toString());

        // Score is computed, not stored — return fixed possible values
        if ($column === 'score') {
            return response()->json(['values' => ['0', '1', '2', '3', '4', '__empty__']]);
        }

        // Matches is computed, not stored — return distinct decree display numbers from evaluated data
        if ($column === 'matches') {
            ['query' => $matchQuery] = $this->buildFilteredQuery($search);
            $records = $matchQuery->get();
            $evaluations = (new MonitoringEvaluator)->evaluateAll($records);

            $decreeDisplays = [];
            foreach ($evaluations as $evaluation) {
                foreach ($evaluation->matches as $match) {
                    $display = $match->awardee->order ? $match->awardee->order->displayNumber() : 'Указ #'.$match->awardee->order_id;
                    $decreeDisplays[] = $display;
                }
            }

            $decreeDisplays = array_values(array_unique($decreeDisplays));
            sort($decreeDisplays, SORT_NATURAL | SORT_FLAG_CASE);
            $decreeDisplays[] = '__empty__';

            return response()->json(['values' => $decreeDisplays]);
        }

        $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];

        if (! in_array($column, $allowedColumns, true)) {
            return response()->json(['values' => []]);
        }

        ['query' => $query] = $this->buildFilteredQuery($search);
        $values = $query->distinct()->pluck($column)->filter()->map(function ($v): string {
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
        })->sort()->values();
        $values[] = '__empty__';

        return response()->json(['values' => $values]);
    }
}
