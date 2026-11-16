<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MonitoringRecord;
use App\Services\Monitoring\MonitoringCsvImporter;
use App\Services\Monitoring\MonitoringEvaluator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MonitoringController extends Controller
{
    public function index(Request $request, MonitoringEvaluator $evaluator): View
    {
        $search = trim($request->string('q')->toString());

        $records = $this->filteredRecords($search)->orderBy('id')->get();
        $evaluations = $evaluator->evaluateAll($records);

        return view('monitoring.index', [
            'evaluations' => $evaluations,
            'search' => $search,
            'totalCount' => MonitoringRecord::query()->count(),
        ]);
    }

    private function filteredRecords(string $search): Builder
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

        return $query;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sheet' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'submission_date' => ['nullable', 'date'],
            'award' => ['nullable', 'string', 'max:255'],
        ]);

        MonitoringRecord::query()->create([
            'sheet' => $validated['sheet'] ?? null,
            'full_name' => trim($validated['full_name']),
            'tax_id' => $validated['tax_id'] ?? null,
            'submission_date' => $validated['submission_date'] ?? null,
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
            'award' => ['nullable', 'string', 'max:255'],
        ]);

        $record->update([
            'sheet' => $validated['sheet'] ?? null,
            'full_name' => trim($validated['full_name']),
            'tax_id' => $validated['tax_id'] ?? null,
            'submission_date' => $validated['submission_date'] ?? null,
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

        if ($file === null) {
            return redirect()->route('monitoring.index')->withErrors(['csv_file' => 'Файл не завантажено.']);
        }

        $imported = $importer->import($file);

        return redirect()->route('monitoring.index')->with('status', "Успішно імпортовано {$imported} записів у таблицю моніторингу.");
    }

    public function export(Request $request, MonitoringEvaluator $evaluator): StreamedResponse
    {
        $query = $this->filteredRecords(trim($request->string('q')->toString()));

        if ($request->isMethod('post')) {
            $validated = $request->validate(['record_ids' => ['required', 'json']]);
            $ids = json_decode($validated['record_ids'], true);
            abort_unless(is_array($ids) && array_is_list($ids) && count(array_filter($ids, fn ($id) => ! is_int($id) || $id < 1)) === 0, 422);
            $query->whereKey($ids);
        }

        $records = $query->orderBy('id')->get();
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
                'Дата подання',
                'Нагорода',
                'Бал',
                'Знайдено в указах',
            ], ';', '"', '');

            foreach ($evaluations as $evaluation) {
                $matchedDecrees = [];
                foreach ($evaluation->matches as $match) {
                    $order = $match->awardee->order;
                    $matchedDecrees[] = ($order ? $order->displayNumber() : 'Указ #'.$match->awardee->order_id)
                        .' (бал: '.$match->score.')';
                }

                fputcsv($handle, [
                    $evaluation->record->sheet ?? '',
                    $evaluation->record->full_name,
                    $evaluation->record->submission_date?->format('d.m.Y') ?? '',
                    $evaluation->record->award ?? '',
                    $evaluation->score,
                    implode('; ', $matchedDecrees),
                ], ';', '"', '');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
