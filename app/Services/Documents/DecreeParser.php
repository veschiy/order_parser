<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DecreeParseException;
use App\Services\Http\BrowserlikeClient;
use App\Services\Names\UkrainianNameNormalizer;

final readonly class DecreeParser
{
    private const string WHITESPACE_PATTERN = '/\s+/u';

    private UkrainianNameNormalizer $normalizer;

    private BrowserlikeClient $client;

    public function __construct(?UkrainianNameNormalizer $normalizer = null, ?BrowserlikeClient $client = null)
    {
        $this->normalizer = $normalizer ?? new UkrainianNameNormalizer;
        $this->client = $client ?? new BrowserlikeClient;
    }

    public function parse(string $url, ?\DateTimeInterface $publishedAt = null): ParsedDecree
    {
        [$status, $body] = $this->request($url);

        if ($status < 200 || $status >= 300) {
            throw new DecreeParseException("Decree request failed with status {$status}.");
        }

        $html = $body;
        $text = $this->htmlToText($html);

        $publishedAt ??= $this->extractPublishedAt($html, $text);

        return new ParsedDecree(
            documentUrl: $url,
            number: $this->extractNumber($text, $url),
            title: $this->extractTitle($html),
            publishedAt: $publishedAt,
            rawText: mb_substr($text, 0, 100000, 'UTF-8'),
            rawHtml: $body,
            awardees: $this->extractAwardees($text),
        );
    }

    /**
     * @return list<ParsedAwardee>
     */
    private function extractAwardees(string $text): array
    {
        $awardees = [];

        $contentStart = mb_strpos($text, 'Нагородити', 0, 'UTF-8') ?: 0;

        foreach (['Президент України', 'Версія для друку', 'Новини Останні'] as $marker) {
            $position = mb_strpos($text, $marker, $contentStart, 'UTF-8');

            if ($position !== false) {
                $text = mb_substr($text, 0, $position, 'UTF-8');
            }
        }

        preg_match_all('/Нагородити\s+(.+)(?=Нагородити\s+|$)/us', $text, $segments, PREG_SET_ORDER);

        foreach ($segments as $segmentMatch) {
            $body = $segmentMatch[1];

            if (! preg_match('/^(.+?)(?=\s+[А-ЯІЇЄҐ][А-ЯІЇЄҐЬ]{2,}\s+[А-ЯІЇЄҐ][а-яіїєґ])/us', $body, $awardMatch, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $award = trim($awardMatch[1][0]);
            $namesText = trim(substr($body, $awardMatch[1][1] + strlen($awardMatch[1][0])));

            foreach ($this->parseNameEntries($namesText, $award) as $awardee) {
                $awardees[] = $awardee;
            }
        }

        if ($awardees === [] && preg_match('/Нагородити/u', $text) === 1) {
            throw new DecreeParseException('Decree parsing incomplete: no awardees were extracted.');
        }

        return $awardees;
    }

    /**
     * @return list<ParsedAwardee>
     */
    private function parseNameEntries(string $namesText, string $award): array
    {
        $candidatePositions = $this->findCandidatePositions($namesText);

        $entries = [];

        for ($i = 0, $count = count($candidatePositions) - 1; $i < $count; $i++) {
            $chunk = trim(mb_substr($namesText, $candidatePositions[$i], $candidatePositions[$i + 1] - $candidatePositions[$i], 'UTF-8'));

            if ($chunk === '') {
                continue;
            }

            $parsed = $this->parseNameRankChunk($chunk);
            if ($parsed === null) {
                continue;
            }

            $entries[] = new ParsedAwardee(
                fullName: $this->normalizer->toNominative($parsed['name']),
                surname: $parsed['surname'] !== '' ? $parsed['surname'] : null,
                givenName: $parsed['given'] !== '' ? $parsed['given'] : null,
                patronymic: $parsed['patronymic'],
                rank: $parsed['rank'] !== '' ? $this->normalizeRank($parsed['rank']) : null,
                award: $this->normalizeAward($award),
                positionNote: $parsed['qualifier'],
            );
        }

        return $entries;
    }

    /**
     * Find the character positions of name candidates in the text.
     *
     * @return list<int>
     */
    private function findCandidatePositions(string $namesText): array
    {
        preg_match_all('/(?:[А-ЯІЇЄҐ\.ʼ\x{2019}]{2,}\s+)+[А-ЯІЇЄҐ][ʼ\x{2019}а-яіїєґ]{2,}(?:-[А-ЯІЇЄҐ][ʼ\x{2019}а-яіїєґ]{2,})?/u', $namesText, $candidateMatches, PREG_OFFSET_CAPTURE);

        $positions = [];
        foreach ($candidateMatches[0] as $candidateMatch) {
            $positions[] = mb_strlen(substr($namesText, 0, $candidateMatch[1]), 'UTF-8');
        }

        $positions[] = mb_strlen($namesText, 'UTF-8');

        return $positions;
    }

    /**
     * Parse a chunk containing "NAME — RANK" format.
     *
     * @return array{name: string, surname: string, given: string, patronymic: string|null, rank: string, qualifier: string|null}|null
     */
    private function parseNameRankChunk(string $chunk): ?array
    {
        if (! preg_match('/^(.+?)\s*(?:—|–|\s-\s)\s*(.+)$/u', $chunk, $matches)) {
            return null;
        }

        $namePart = trim($matches[1]);
        $qualifier = $this->extractQualifier($namePart);
        if ($qualifier['qualifier'] !== null) {
            $namePart = $qualifier['name'];
        }

        $rank = $this->cleanRank(trim($matches[2]));

        $nameTokens = preg_split(self::WHITESPACE_PATTERN, $namePart) ?: [];

        return [
            'name' => $namePart,
            'surname' => $nameTokens[0] ?? '',
            'given' => $nameTokens[1] ?? '',
            'patronymic' => count($nameTokens) > 2 ? implode(' ', array_slice($nameTokens, 2)) : null,
            'rank' => $rank,
            'qualifier' => $qualifier['qualifier'],
        ];
    }

    /**
     * Extract qualifier from a name part like "Name (qualifier)".
     *
     * @return array{name: string, qualifier: string|null}
     */
    private function extractQualifier(string $namePart): array
    {
        if (preg_match('/^(.*)\s*\(([^)]+)\)\s*$/u', $namePart, $match) === 1) {
            return [
                'name' => trim($match[1]),
                'qualifier' => trim($match[2]),
            ];
        }

        return ['name' => $namePart, 'qualifier' => null];
    }

    /**
     * Clean up rank string by removing abbreviations and trailing organization references.
     */
    private function cleanRank(string $rank): string
    {
        $rankParts = preg_split(self::WHITESPACE_PATTERN, $rank) ?: [];
        $cleanRankParts = [];

        foreach ($rankParts as $rankPart) {
            if ($cleanRankParts !== [] && preg_match('/^[А-ЯІЇЄҐ]{3,}$/u', $rankPart) === 1) {
                break;
            }

            $cleanRankParts[] = $rankPart;
        }

        $rank = trim(implode(' ', $cleanRankParts));
        $rank = preg_replace('/\s+(?:а|у)\s+(?:Національній|Державній|Спеціальній|Державній\s+прикордонній).+$/u', '', $rank) ?? $rank;

        return trim($rank);
    }

    private function normalizeRank(string $rank): string
    {
        $rank = trim($rank);

        if ($rank === '') {
            return '';
        }

        $parts = preg_split(self::WHITESPACE_PATTERN, $rank) ?: [];

        foreach ($parts as $key => $word) {
            $parts[$key] = implode('-', array_map($this->normalizeRankSegment(...), explode('-', $word)));
        }

        return implode(' ', $parts);
    }

    /**
     * Normalize a single segment of a rank (e.g., "генерал" from "генерал-майор").
     */
    private function normalizeRankSegment(string $segment): string
    {
        $segment = trim($segment, '.');

        if ($segment === '') {
            return '';
        }

        $lower = mb_strtolower($segment, 'UTF-8');

        if (str_ends_with($lower, 'ого') && mb_strlen($segment, 'UTF-8') > 3) {
            return mb_substr($segment, 0, -3, 'UTF-8').'ий';
        }

        if (str_ends_with($lower, 'у')) {
            return mb_substr($segment, 0, -1, 'UTF-8').'а';
        }

        if (str_ends_with($lower, 'а')) {
            return mb_substr($segment, 0, -1, 'UTF-8');
        }

        return $segment;
    }

    private function normalizeAward(string $award): string
    {
        $normalized = preg_replace('/^орденом\b/u', 'орден', $award) ?? $award;
        $normalized = preg_replace('/^медаллю\b/u', 'медаль', $normalized) ?? $normalized;
        $normalized = preg_replace('/^почесною\s+відзнакою\b/u', 'почесна відзнака', $normalized) ?? $normalized;
        $normalized = preg_replace('/^відзнакою\b/u', 'відзнака', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function extractNumber(string $text, string $url): ?string
    {
        if (preg_match('/№\s*(\d+(?:\s*\/\s*\d+)?)/u', $text, $matches)) {
            return preg_replace('/\s+/', '', $matches[1]);
        }

        if (preg_match('/\/(\d+\/\d+)\//', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractTitle(string $html): ?string
    {
        $title = null;

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/us', $html, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/us', $html, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $title = preg_replace('/\s+[—–-].*$/u', '', $title) ?? $title;
        }

        return $title !== '' ? $title : null;
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/us', ' ', $html) ?? $html;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/us', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Keep hyphenated names compact when markup inserts whitespace around them.
        $text = preg_replace('/[\x{2010}\x{2011}\x{2012}]/u', '-', $text) ?? $text;
        $text = preg_replace('/(\p{L})\s*-\s*(\p{L})/u', '\1-\2', $text) ?? $text;
        $text = preg_replace(self::WHITESPACE_PATTERN, ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractPublishedAt(string $html, string $text): ?\DateTimeInterface
    {
        $date = $this->extractDateFromHtml($html);
        if ($date instanceof \DateTimeInterface) {
            return $date;
        }

        return $this->extractDateFromText($text);
    }

    private function extractDateFromHtml(string $html): ?\DateTimeInterface
    {
        foreach ([
            '/property=["\']article:published_time["\'][^>]*content=["\']([^"\']+)["\']/iu',
            '/content=["\']([^"\']+)["\'][^>]*property=["\']article:published_time["\']/iu',
            '/<time[^>]*datetime=["\']([^"\']+)["\'][^>]*>/iu',
            '/<meta[^>]*name=["\']date["\'][^>]*content=["\']([^"\']+)["\']/iu',
        ] as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                try {
                    return new \DateTimeImmutable($matches[1]);
                } catch (\Throwable) {
                    // Try the next extraction strategy.
                }
            }
        }

        return null;
    }

    private function extractDateFromText(string $text): ?\DateTimeInterface
    {
        $months = [
            'січня' => 1,
            'лютого' => 2,
            'березня' => 3,
            'квітня' => 4,
            'травня' => 5,
            'червня' => 6,
            'липня' => 7,
            'серпня' => 8,
            'вересня' => 9,
            'жовтня' => 10,
            'листопада' => 11,
            'грудня' => 12,
        ];

        preg_match_all('/(\d{1,2})\s+('.implode('|', array_keys($months)).')\s+(\d{4})/u', $text, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return null;
        }

        $lastMatch = end($matches);
        $day = (int) $lastMatch[1];
        $month = $months[$lastMatch[2]];
        $year = (int) $lastMatch[3];

        try {
            return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function request(string $url): array
    {
        return $this->client->fetch($url, $this->headers());
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'uk,uk-UA;q=0.9,ru;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate',
            'Referer' => 'https://www.president.gov.ua/',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Sec-Ch-Ua' => '"Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
        ];
    }
}
