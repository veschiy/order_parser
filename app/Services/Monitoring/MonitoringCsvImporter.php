<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringRecord;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Throwable;

final class MonitoringCsvImporter
{
    /**
     * @return int Number of imported records
     */
    public function import(UploadedFile|string $fileOrContent): int
    {
        $content = $this->loadContent($fileOrContent);
        if ($content === null) {
            return 0;
        }

        $stream = $this->createStream($content);
        if ($stream === null) {
            return 0;
        }

        $delimiter = $this->findDelimiterAndRewind($stream);
        $imported = 0;
        if ($delimiter !== null) {
            $imported = $this->importFromStream($stream, $delimiter);
        } else {
            fclose($stream);
        }

        return $imported;
    }

    /**
     * @param  resource  $stream
     */
    private function importFromStream($stream, string $delimiter): int
    {
        rewind($stream);

        $firstRow = fgetcsv($stream, 0, $delimiter, '"', '\\');
        if ($firstRow === false) {
            fclose($stream);

            return 0;
        }

        $columnIndices = $this->resolveColumnIndices($firstRow, $stream);
        $imported = $this->importRows($stream, $delimiter, $columnIndices);

        fclose($stream);

        return $imported;
    }

    /**
     * Load and prepare content from file or string. Returns null if empty.
     */
    private function loadContent(UploadedFile|string $fileOrContent): ?string
    {
        $content = $fileOrContent instanceof UploadedFile
            ? (string) file_get_contents($fileOrContent->getRealPath())
            : $fileOrContent;

        $content = $this->prepareContent($content);

        if (trim($content) === '') {
            return null;
        }

        return $content;
    }

    /**
     * Create a memory stream with the content written to it.
     *
     * @return resource|null
     */
    private function createStream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return null;
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * @param  resource  $stream
     */
    private function findDelimiterAndRewind($stream): ?string
    {
        $firstLine = $this->readFirstNonEmptyLine($stream);
        if ($firstLine === '') {
            return null;
        }

        return $this->detectDelimiter($firstLine);
    }

    /**
     * @param  resource  $stream
     */
    private function readFirstNonEmptyLine($stream): string
    {
        $firstLine = '';
        while (($line = fgets($stream)) !== false) {
            if (trim($line) !== '') {
                $firstLine = $line;
                break;
            }
        }

        return $firstLine;
    }

    /**
     * @param  array<int, string|null>  $firstRow
     * @param  resource  $stream
     * @return array<string, int>
     */
    private function resolveColumnIndices(array $firstRow, $stream): array
    {
        $headerMap = $this->extractHeaderMap($firstRow);

        if ($headerMap === null) {
            rewind($stream);

            return [
                'sheet' => 0,
                'full_name' => 1,
                'submission_date' => 2,
                'award' => 3,
            ];
        }

        return $headerMap;
    }

    /**
     * Import data rows from the CSV stream.
     *
     * @param  resource  $stream
     * @param  array<string, int>  $columnIndices
     * @return int Number of imported records
     */
    private function importRows($stream, string $delimiter, array $columnIndices): int
    {
        $imported = 0;

        while (($row = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $record = $this->rowToRecord($row, $columnIndices);
            if ($record === null) {
                continue;
            }

            MonitoringRecord::query()->create($record);
            $imported++;
        }

        return $imported;
    }

    /**
     * Convert a CSV row to a MonitoringRecord attributes array.
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columnIndices
     * @return array{sheet: string|null, full_name: string, tax_id: null, submission_date: DateTimeInterface|null, rank: string|null, award: string|null}|null
     */
    private function rowToRecord(array $row, array $columnIndices): ?array
    {
        $fullName = $this->getFieldValue($row, $columnIndices, 'full_name');
        if ($fullName === '') {
            return null;
        }

        $sheet = $this->getOptionalFieldValue($row, $columnIndices, 'sheet');
        $rank = $this->getOptionalFieldValue($row, $columnIndices, 'rank');
        $award = $this->getOptionalFieldValue($row, $columnIndices, 'award');

        $submissionDate = $this->parseSubmissionDate($row, $columnIndices);

        return [
            'sheet' => $sheet,
            'full_name' => $fullName,
            'tax_id' => null,
            'submission_date' => $submissionDate,
            'rank' => $rank,
            'award' => $award,
        ];
    }

    /**
     * Get a trimmed field value from a row by column index mapping.
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columnIndices
     */
    private function getFieldValue(array $row, array $columnIndices, string $field): string
    {
        return isset($columnIndices[$field]) && isset($row[$columnIndices[$field]])
            ? trim((string) $row[$columnIndices[$field]])
            : '';
    }

    /**
     * Get an optional trimmed field value (returns null if empty).
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columnIndices
     */
    private function getOptionalFieldValue(array $row, array $columnIndices, string $field): ?string
    {
        $value = $this->getFieldValue($row, $columnIndices, $field);

        return $value !== '' ? $value : null;
    }

    /**
     * Parse the submission date from a row.
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columnIndices
     */
    private function parseSubmissionDate(array $row, array $columnIndices): ?DateTimeInterface
    {
        $dateStrings = [];
        if (isset($columnIndices['submission_date']) && isset($row[$columnIndices['submission_date']])) {
            $dateStrings[] = (string) $row[$columnIndices['submission_date']];
        }

        return $this->parseLatestDate($dateStrings);
    }

    private function prepareContent(string $content): string
    {
        // Strip UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Convert encoding if not UTF-8
        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
            if (mb_check_encoding($converted, 'UTF-8')) {
                $content = $converted;
            }
        }

        return $content;
    }

    private function detectDelimiter(string $firstLine): string
    {
        $delimiters = [',', ';', "\t"];
        $bestDelimiter = ',';
        $maxCount = -1;

        foreach ($delimiters as $del) {
            $count = substr_count($firstLine, $del);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $del;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<string, int>|null
     */
    private function extractHeaderMap(array $row): ?array
    {
        $map = [];

        foreach ($row as $index => $cell) {
            if ($cell === null) {
                continue;
            }

            $normalized = mb_strtolower(trim($cell), 'UTF-8');
            $normalized = str_replace(['.', '_', '-'], '', $normalized);

            if ($this->isSkippedHeader($normalized)) {
                continue;
            }

            $field = $this->matchHeaderField($normalized);
            if ($field !== null) {
                $map[$field] = $index;
            }
        }

        if (isset($map['full_name']) || count($map) >= 2) {
            return $map;
        }

        return null;
    }

    private function isSkippedHeader(string $normalized): bool
    {
        return str_contains($normalized, 'отриман')
            || str_contains($normalized, 'received')
            || str_contains($normalized, 'іпн')
            || str_contains($normalized, 'рнокпп')
            || str_contains($normalized, 'tax')
            || str_contains($normalized, 'код')
            || $normalized === 'ipn';
    }

    private function matchHeaderField(string $normalized): ?string
    {
        $checks = [
            'sheet' => fn (): bool => str_contains($normalized, 'аркуш')
                || str_contains($normalized, 'sheet')
                || str_contains($normalized, 'лист'),
            'full_name' => fn (): bool => str_contains($normalized, 'піб')
                || str_contains($normalized, 'фіо')
                || str_contains($normalized, 'fullname')
                || str_contains($normalized, 'full name')
                || str_contains($normalized, 'особа')
                || $normalized === 'name'
                || $normalized === "ім'я"
                || $normalized === 'імя'
                || $normalized === 'прізвище',
            'submission_date' => fn (): bool => str_contains($normalized, 'поданн')
                || str_contains($normalized, 'submission')
                || str_contains($normalized, 'date')
                || str_contains($normalized, 'дата'),
            'rank' => fn (): bool => str_contains($normalized, 'званн')
                || str_contains($normalized, 'ранг')
                || str_contains($normalized, 'grade')
                || str_contains($normalized, 'rank')
                || str_contains($normalized, 'должност'),
            'award' => fn (): bool => str_contains($normalized, 'нагород')
                || str_contains($normalized, 'award')
                || str_contains($normalized, 'відзнак'),
        ];

        foreach ($checks as $field => $check) {
            if ($check()) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isRowEmpty(array $row): bool
    {
        return array_all($row, fn ($cell): bool => $cell === null || trim($cell) === '');
    }

    /**
     * @param  list<string>|string|null  $rawDates
     */
    public function parseLatestDate(array|string|null $rawDates): ?DateTimeInterface
    {
        if ($rawDates === null) {
            return null;
        }

        $inputStrings = is_array($rawDates) ? $rawDates : [$rawDates];
        $allFoundDates = [];

        foreach ($inputStrings as $input) {
            $input = trim($input);
            if ($input === '') {
                continue;
            }

            $cleaned = str_replace(['./', '/.'], '.', $input);

            foreach ([
                ['pattern' => '/\b(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})\b/', 'yearFirst' => false],
                ['pattern' => '/\b(\d{4})[\.\/\-](\d{1,2})[\.\/\-](\d{1,2})\b/', 'yearFirst' => true],
            ] as $config) {
                if (preg_match_all($config['pattern'], $cleaned, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $date = $this->createDateFromMatch($m, $config['yearFirst']);
                        if ($date instanceof DateTimeInterface) {
                            $allFoundDates[] = $date;
                        }
                    }
                }
            }
        }

        if ($allFoundDates === []) {
            return null;
        }

        $latest = $allFoundDates[0];
        foreach ($allFoundDates as $date) {
            if ($date->getTimestamp() > $latest->getTimestamp()) {
                $latest = $date;
            }
        }

        return $latest;
    }

    /**
     * Create a date from a regex match.
     *
     * @param  array<int, string>  $match
     * @param  bool  $yearFirst  true if the pattern is Y-m-d, false if d-m-Y
     */
    private function createDateFromMatch(array $match, bool $yearFirst): ?DateTimeInterface
    {
        try {
            if ($yearFirst) {
                return Carbon::createFromDate((int) (string) $match[1], (int) (string) $match[2], (int) (string) $match[3])->startOfDay();
            }

            return Carbon::createFromDate((int) (string) $match[3], (int) (string) $match[2], (int) (string) $match[1])->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
