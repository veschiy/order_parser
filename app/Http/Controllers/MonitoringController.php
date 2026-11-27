<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MonitoringRecord;
use App\Services\Monitoring\MonitoringCsvExportService;
use App\Services\Monitoring\MonitoringCsvImporter;
use App\Services\Monitoring\MonitoringEvaluationCache;
use App\Services\Monitoring\MonitoringQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MonitoringController extends Controller
{
    /** Maximum byte length of the posted JSON selection before it is validated or decoded. */
    private const int MAX_RECORD_IDS_JSON_BYTES = 20_000;

    /** Maximum record IDs accepted from the visible-table export workflow. */
    private const int MAX_SELECTED_RECORD_IDS = 500;

    public function index(Request $request, MonitoringQueryService $queryService): View
    {
        $search = trim($request->string('q')->toString());

        ['query' => $query] = $queryService->buildFilteredQuery($search);
        $records = $query->paginate(15);

        return view('monitoring.index', [
            'paginator' => $records,
            'search' => $search,
            'totalCount' => MonitoringRecord::query()->count(),
        ]);
    }

    public function store(Request $request, MonitoringEvaluationCache $evaluationCache): RedirectResponse
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
        $evaluationCache->invalidate();

        return redirect()->route('monitoring.index')->with('status', 'Запис моніторингу успішно додано.');
    }

    public function update(Request $request, MonitoringEvaluationCache $evaluationCache, int $id): RedirectResponse
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
        $evaluationCache->invalidate();

        return redirect()->route('monitoring.index')->with('status', 'Запис моніторингу оновлено.');
    }

    public function destroy(MonitoringEvaluationCache $evaluationCache, int $id): RedirectResponse
    {
        $record = MonitoringRecord::query()->findOrFail($id);
        $record->delete();

        $evaluationCache->invalidate();

        return redirect()->route('monitoring.index')->with('status', 'Запис моніторингу видалено.');
    }

    public function clear(MonitoringEvaluationCache $evaluationCache): RedirectResponse
    {
        MonitoringRecord::query()->delete();
        $evaluationCache->invalidate();

        return redirect()->route('monitoring.index')->with('status', 'Усі записи моніторингу видалено.');
    }

    public function import(Request $request, MonitoringCsvImporter $importer, MonitoringEvaluationCache $evaluationCache): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('csv_file');

        if (! $file instanceof UploadedFile) {
            return redirect()->route('monitoring.index')->withErrors(['csv_file' => 'Файл не завантажено.']);
        }

        $imported = $importer->import($file);
        if ($imported > 0) {
            $evaluationCache->invalidate();
        }

        return redirect()->route('monitoring.index')->with('status', "Успішно імпортовано {$imported} записів у таблицю моніторингу.");
    }

    public function export(Request $request, MonitoringQueryService $queryService, MonitoringCsvExportService $exportService): StreamedResponse
    {
        ['query' => $query] = $queryService->buildFilteredQuery(trim($request->string('q')->toString()));

        if ($request->isMethod('post')) {
            $rawIds = $request->input('record_ids');
            if (! is_string($rawIds)) {
                abort(422);
            }

            if (strlen($rawIds) > self::MAX_RECORD_IDS_JSON_BYTES) {
                abort(422);
            }

            $request->validate(['record_ids' => ['required', 'string', 'json']]);
            $ids = json_decode($rawIds, true);
            abort_unless(
                is_array($ids)
                    && array_is_list($ids)
                    && count($ids) <= self::MAX_SELECTED_RECORD_IDS
                    && count(array_filter($ids, fn ($id): bool => ! is_int($id) || $id < 1)) === 0,
                422,
            );
            $query->whereKey($ids);
        }

        return $exportService->stream($query->get());
    }

    public function indexJson(Request $request, MonitoringQueryService $queryService, MonitoringEvaluationCache $evaluationCache): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        $perPage = $request->integer('perPage', 15);
        $page = max(1, $request->integer('page', 1));
        $sort = $request->string('sort')->toString() ?: null;
        $direction = $request->string('direction')->toString() ?: 'asc';
        $filtersRaw = $request->input('filters', []);
        /** @var array<string> $filters */
        $filters = is_array($filtersRaw) ? array_filter($filtersRaw, is_string(...)) : [];

        $responseData = $queryService->indexData($search, array_values($filters), $sort, $direction, $page, $perPage, $evaluationCache);

        return response()->json($responseData);
    }

    /**
     * Return distinct values for a given column, used by the filter dropdown UI.
     */
    public function distinctJson(Request $request, MonitoringQueryService $queryService, MonitoringEvaluationCache $evaluationCache): JsonResponse
    {
        $columnString = $request->string('column')->toString();
        $column = $columnString !== '' ? $columnString : null;
        $search = trim($request->string('q')->toString());

        $values = $queryService->distinctValues($column, $search, $evaluationCache);

        return response()->json(['values' => $values]);
    }
}
