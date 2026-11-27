<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringRecord;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;

final readonly class MonitoringCsvImporter
{
    public function __construct(private MonitoringCsvParser $parser = new MonitoringCsvParser) {}

    /**
     * @return int Number of imported records
     */
    public function import(UploadedFile|string $fileOrContent): int
    {
        $content = $fileOrContent instanceof UploadedFile
            ? (string) file_get_contents($fileOrContent->getRealPath())
            : $fileOrContent;

        $records = $this->parser->parse($content);
        foreach ($records as $record) {
            MonitoringRecord::query()->create($record);
        }

        return count($records);
    }

    /**
     * @param  list<string>|string|null  $rawDates
     */
    public function parseLatestDate(array|string|null $rawDates): ?DateTimeInterface
    {
        return $this->parser->parseLatestDate($rawDates);
    }
}
