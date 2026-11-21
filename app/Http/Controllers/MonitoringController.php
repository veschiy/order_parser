<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MonitoringRecord;
use App\Services\Monitoring\MonitoringCsvImporter;
use App\Services\Monitoring\MonitoringEvaluator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
     * @return array{query: Builder<MonitoringRecord>, scoreFilter: int[]|null}
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

        // Apply column filters (score is computed, handled separately)
        $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];
        $scoreFilter = null;

        foreach ($filters as $filter) {
            $parts = explode(':', $filter, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $column = $parts[0];
            $values = explode(',', $parts[1]);

            if ($column === 'score') {
                $scoreFilter = array_map('intval', $values);

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

        return ['query' => $query, 'scoreFilter' => $scoreFilter];
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
                    $matchedDecrees[] = $order->displayNumber().' (бал: '.$match->score.')';
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
        $perPage = (int) $request->input('perPage', 15);
        $page = max(1, (int) $request->input('page', 1));
        $sort = $request->input('sort');
        $direction = $request->input('direction', 'asc');
        $filters = (array) $request->input('filters', []);

        ['query' => $query, 'scoreFilter' => $scoreFilter] = $this->buildFilteredQuery($search, $filters, $sort, $direction);

        if ($scoreFilter !== null) {
            // Score is computed, not stored — must evaluate all records first, then filter and paginate
            $records = $query->get();
            $evaluations = $evaluator->evaluateAll($records);
            $evaluations = $evaluations->filter(fn ($evaluation) => in_array($evaluation->score, $scoreFilter, true));

            // Apply score sorting post-evaluation
            if ($sort === 'score') {
                $evaluations = $evaluations->sortBy(fn ($evaluation) => $evaluation->score, sortFlags: SORT_NUMERIC, descending: $direction === 'desc');
            }

            $total = $evaluations->count();
            $evaluations = $evaluations->forPage($page, $perPage);
        } else {
            // No score filter — paginate at the DB level for efficiency
            $total = $query->count();
            $records = $query->forPage($page, $perPage)->get();
            $evaluations = $evaluator->evaluateAll($records);

            // Apply score sorting post-evaluation (score is computed, not a DB column)
            if ($sort === 'score') {
                $evaluations = $evaluations->sortBy(fn ($evaluation) => $evaluation->score, sortFlags: SORT_NUMERIC, descending: $direction === 'desc');
            }
        }

        return response()->json([
            'data' => $evaluations->map(fn ($evaluation) => [
                'id' => $evaluation->record->id,
                'sheet' => $evaluation->record->sheet ?? '',
                'full_name' => $evaluation->record->full_name,
                'submission_date' => $evaluation->record->submission_date?->format('Y-m-d') ?? '',
                'submission_date_display' => $evaluation->record->submission_date?->format('d.m.Y') ?? '—',
                'award' => $evaluation->record->award ?? '',
                'rank' => $evaluation->record->rank ?? '',
                'score' => $evaluation->score,
                'matches' => array_map(static fn ($m) => [
                    'awardee_id' => $m->awardee->id,
                    'order_id' => $m->awardee->order_id,
                    'order_display' => $m->awardee->order ? $m->awardee->order->displayNumber() : 'Указ #'.$m->awardee->order_id,
                    'award' => $m->awardee->award ?? '—',
                    'score' => $m->score,
                    'rankMatched' => $m->rankMatched,
                    'awardMatched' => $m->awardMatched,
                    'dateMatched' => $m->dateMatched,
                ], $evaluation->matches),
                'matchesSummary' => empty($evaluation->matches)
                    ? 'Не знайдено'
                    : implode(', ', array_map(static fn ($m) => ($m->awardee->order ? $m->awardee->order->displayNumber() : 'Указ #'.$m->awardee->order_id), $evaluation->matches)),
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
     */
    private function applyColumnFilter(Builder $query, string $column, array $values): void
    {
        $hasEmpty = in_array('__empty__', $values, true);
        $nonEmptyValues = array_values(array_filter($values, fn ($v) => $v !== '__empty__'));

        if ($hasEmpty && empty($nonEmptyValues)) {
            // Only empty filter — match null or empty string
            $query->where(function ($builder) use ($column): void {
                $builder->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($hasEmpty) {
            // Both empty and non-empty values
            $query->where(function ($builder) use ($column, $nonEmptyValues): void {
                $builder->whereIn($column, $nonEmptyValues)->orWhere(function ($inner) use ($column): void {
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

        $allowedColumns = ['sheet', 'rank', 'full_name', 'submission_date', 'award'];

        if (! in_array($column, $allowedColumns, true)) {
            return response()->json(['values' => []]);
        }

        ['query' => $query] = $this->buildFilteredQuery($search);
        $values = $query->distinct()->pluck($column)->filter()->map(fn ($v) => (string) $v)->sort()->values();
        $values[] = '__empty__';

        return response()->json(['values' => $values]);
    }
}
