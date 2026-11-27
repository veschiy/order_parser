<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringRecord;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MonitoringCsvExportService
{
    private const string DECREE_PREFIX = 'Указ #';

    public function __construct(private MonitoringEvaluator $evaluator) {}

    /**
     * @param  Collection<int, MonitoringRecord>  $records
     */
    public function stream(Collection $records): StreamedResponse
    {
        $evaluations = $this->evaluator->evaluateAll($records);

        return response()->stream(function () use ($evaluations): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

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
                fputcsv($handle, $this->evaluationRow($evaluation), ';', '"', '');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="monitoring_export.csv"',
        ]);
    }

    /**
     * @return array{string, string, string, string, string, int, string}
     */
    private function evaluationRow(MonitoringEvaluation $evaluation): array
    {
        $matchedDecrees = [];

        foreach ($evaluation->matches as $match) {
            $displayNumber = $match->awardee->order?->displayNumber()
                ?? self::DECREE_PREFIX.$match->awardee->order_id;
            $matchedDecrees[] = $displayNumber.' (бал: '.$match->score.')';
        }

        return [
            $evaluation->record->sheet ?? '',
            $evaluation->record->full_name,
            $evaluation->record->rank ?? '',
            $evaluation->record->submission_date?->format('d.m.Y') ?? '',
            $evaluation->record->award ?? '',
            $evaluation->score,
            implode('; ', $matchedDecrees),
        ];
    }
}
