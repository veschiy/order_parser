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
        if ($delimiter === null) {
            fclose($stream);

            return 0;
        }

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
     * Find the first non-empty line, detect delimiter, and rewind. Returns null if no content.
     *
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
     * Read the first non-empty line from the stream.
     *
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
     * Resolve column indices from the first row, handling header and non-header cases.
     *
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

            // Skip "отримано" or "received" explicitly
            if (str_contains($normalized, 'отриман') || str_contains($normalized, 'received')) {
                continue;
            }

            if (str_contains($normalized, 'аркуш') || str_contains($normalized, 'sheet') || str_contains($normalized, 'лист')) {
                $map['sheet'] = $index;
            } elseif (
                str_contains($normalized, 'піб') ||
                str_contains($normalized, 'фіо') ||
                str_contains($normalized, 'fullname') ||
                str_contains($normalized, 'full name') ||
                str_contains($normalized, 'особа') ||
                $normalized === 'name' ||
                $normalized === "ім'я" ||
                $normalized === 'імя' ||
                $normalized === 'прізвище'
            ) {
                $map['full_name'] = $index;
            } elseif (
                str_contains($normalized, 'іпн') ||
                str_contains($normalized, 'рнокпп') ||
                str_contains($normalized, 'tax') ||
                str_contains($normalized, 'код') ||
                $normalized === 'ipn'
            ) {
                // IPN is intentionally skipped for monitoring
                continue;
            } elseif (
                str_contains($normalized, 'поданн') ||
                str_contains($normalized, 'submission') ||
                str_contains($normalized, 'date') ||
                str_contains($normalized, 'дата')
            ) {
                $map['submission_date'] = $index;
            } elseif (
                str_contains($normalized, 'званн') ||
                str_contains($normalized, 'ранг') ||
                str_contains($normalized, 'grade') ||
                str_contains($normalized, 'rank') ||
                str_contains($normalized, 'должност')
            ) {
                $map['rank'] = $index;
            } elseif (
                str_contains($normalized, 'нагород') ||
                str_contains($normalized, 'award') ||
                str_contains($normalized, 'відзнак')
            ) {
                $map['award'] = $index;
            }
        }

        // We consider it a header row if at least 'full_name' or 2 other fields are recognized
        if (isset($map['full_name']) || count($map) >= 2) {
            return $map;
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

            // Extract all occurrences of d.m.Y, d/m/Y, d-m-Y, Y-m-d, Y/m/d, Y.m.d
            // Also cleans up formatting like 28./05./2026
            $cleaned = str_replace(['./', '/.'], '.', $input);

            if (preg_match_all('/\b(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})\b/', $cleaned, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    try {
                        $allFoundDates[] = Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
                    } catch (Throwable) {
                        // ignore invalid dates
                    }
                }
            }

            if (preg_match_all('/\b(\d{4})[\.\/\-](\d{1,2})[\.\/\-](\d{1,2})\b/', $cleaned, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    try {
                        $allFoundDates[] = Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
                    } catch (Throwable) {
                        // ignore invalid dates
                    }
                }
            }
        }

        if ($allFoundDates === []) {
            return null;
        }

        // Return the latest date found
        $latest = $allFoundDates[0];
        foreach ($allFoundDates as $date) {
            if ($date->isAfter($latest)) {
                $latest = $date;
            }
        }

        return $latest;
    }

    public function parseDate(?string $dateStr): ?DateTimeInterface
    {
        return $this->parseLatestDate($dateStr);
    }
}
