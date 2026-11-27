<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

final class MonitoringCsvParser
{
    /** @var list<array{pattern: string, yearFirst: bool}> */
    private const array DATE_PATTERNS = [
        ['pattern' => '/\b(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{4})\b/', 'yearFirst' => false],
        ['pattern' => '/\b(\d{4})[\.\/-](\d{1,2})[\.\/-](\d{1,2})\b/', 'yearFirst' => true],
    ];

    /**
     * @return list<array{sheet: string|null, full_name: string, tax_id: null, submission_date: DateTimeInterface|null, rank: string|null, award: string|null}>
     */
    public function parse(string $content): array
    {
        $content = $this->prepareContent($content);
        if (trim($content) === '') {
            return [];
        }

        $stream = $this->createStream($content);
        if ($stream === null) {
            return [];
        }

        try {
            return $this->parseStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return list<array{sheet: string|null, full_name: string, tax_id: null, submission_date: DateTimeInterface|null, rank: string|null, award: string|null}>
     */
    private function parseStream(mixed $stream): array
    {
        $delimiter = $this->findDelimiter($stream);
        if ($delimiter === null) {
            return [];
        }

        rewind($stream);
        $firstRow = fgetcsv($stream, 0, $delimiter, '"', '\\');
        if ($firstRow === false) {
            return [];
        }

        $columnIndices = $this->resolveColumnIndices($firstRow, $stream);

        return $this->parseRows($stream, $delimiter, $columnIndices);
    }

    /**
     * Create a memory stream with the content written to it.
     *
     * @return resource|null
     */
    private function createStream(string $content): mixed
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
    private function findDelimiter(mixed $stream): ?string
    {
        $firstLine = '';
        while (($line = fgets($stream)) !== false) {
            if (trim($line) !== '') {
                $firstLine = $line;
                break;
            }
        }

        if ($firstLine === '') {
            return null;
        }

        $delimiters = [',', ';', "\t"];
        $bestDelimiter = ',';
        $maxCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @param  array<int, string|null>  $firstRow
     * @param  resource  $stream
     * @return array<string, int>
     */
    private function resolveColumnIndices(array $firstRow, mixed $stream): array
    {
        $headerMap = $this->extractHeaderMap($firstRow);

        if ($headerMap !== null) {
            return $headerMap;
        }

        rewind($stream);

        return [
            'sheet' => 0,
            'full_name' => 1,
            'submission_date' => 2,
            'award' => 3,
        ];
    }

    /**
     * @param  resource  $stream
     * @param  array<string, int>  $columnIndices
     * @return list<array{sheet: string|null, full_name: string, tax_id: null, submission_date: DateTimeInterface|null, rank: string|null, award: string|null}>
     */
    private function parseRows(mixed $stream, string $delimiter, array $columnIndices): array
    {
        $records = [];

        while (($row = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $record = $this->rowToRecord($row, $columnIndices);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
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

        return [
            'sheet' => $this->getOptionalFieldValue($row, $columnIndices, 'sheet'),
            'full_name' => $fullName,
            'tax_id' => null,
            'submission_date' => $this->parseSubmissionDate($row, $columnIndices),
            'rank' => $this->getOptionalFieldValue($row, $columnIndices, 'rank'),
            'award' => $this->getOptionalFieldValue($row, $columnIndices, 'award'),
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
        if (! isset($columnIndices['submission_date'], $row[$columnIndices['submission_date']])) {
            return null;
        }

        return $this->parseLatestDate((string) $row[$columnIndices['submission_date']]);
    }

    private function prepareContent(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
            if (mb_check_encoding($converted, 'UTF-8')) {
                $content = $converted;
            }
        }

        return $content;
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

        return isset($map['full_name']) || count($map) >= 2 ? $map : null;
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
        return array_all($row, fn (?string $cell): bool => $cell === null || trim($cell) === '');
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
            array_push($allFoundDates, ...$this->datesFromInput($cleaned));
        }

        return $this->latestDate($allFoundDates);
    }

    /**
     * @return list<DateTimeInterface>
     */
    private function datesFromInput(string $input): array
    {
        $dates = [];

        foreach (self::DATE_PATTERNS as $config) {
            if (preg_match_all($config['pattern'], $input, $matches, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $date = $this->createDateFromMatch($match, $config['yearFirst']);
                if ($date instanceof DateTimeInterface) {
                    $dates[] = $date;
                }
            }
        }

        return $dates;
    }

    /**
     * @param  list<DateTimeInterface>  $dates
     */
    private function latestDate(array $dates): ?DateTimeInterface
    {
        if ($dates === []) {
            return null;
        }

        $latest = $dates[0];
        foreach ($dates as $date) {
            if ($date->getTimestamp() > $latest->getTimestamp()) {
                $latest = $date;
            }
        }

        return $latest;
    }

    /**
     * @param  array<int, string>  $match
     */
    private function createDateFromMatch(array $match, bool $yearFirst): ?DateTimeInterface
    {
        try {
            if ($yearFirst) {
                return Carbon::createFromDate((int) $match[1], (int) $match[2], (int) $match[3])->startOfDay();
            }

            return Carbon::createFromDate((int) $match[3], (int) $match[2], (int) $match[1])->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
