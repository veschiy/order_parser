<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Services\Http\BrowserlikeClient;
use App\Services\Names\UkrainianNameNormalizer;
use RuntimeException;

final readonly class DecreeParser
{
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
            throw new RuntimeException("Decree request failed with status {$status}.");
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

        preg_match_all('/Нагородити\s+(.+?)(?=Нагородити\s+|$)/us', $text, $segments, PREG_SET_ORDER);

        foreach ($segments as $segmentMatch) {
            $body = $segmentMatch[1];

            if (! preg_match('/^(.*?)(?=\s+[А-ЯІЇЄҐ][А-ЯІЇЄҐЬ]{2,}\s+[А-ЯІЇЄҐ][а-яіїєґ])/us', $body, $awardMatch, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $award = trim($awardMatch[1][0]);
            $namesText = trim(substr($body, $awardMatch[1][1] + strlen($awardMatch[1][0])));

            foreach ($this->parseNameEntries($namesText, $award) as $awardee) {
                $awardees[] = $awardee;
            }
        }

        if ($awardees === [] && preg_match('/Нагородити/u', $text) === 1) {
            throw new RuntimeException('Decree parsing incomplete: no awardees were extracted.');
        }

        return $awardees;
    }

    /**
     * @return list<ParsedAwardee>
     */
    private function parseNameEntries(string $namesText, string $award): array
    {
        $entries = [];

        preg_match_all('/(?:[А-ЯІЇЄҐ\.ʼ’\']{2,}\s+)+[А-ЯІЇЄҐ][ʼ’а-яіїєґ]{2,}(?:-[А-ЯІЇЄҐ][ʼ’а-яіїєґ]{2,})?/u', $namesText, $candidateMatches, PREG_OFFSET_CAPTURE);
        $candidatePositions = [];

        foreach ($candidateMatches[0] as $candidateMatch) {
            $candidatePositions[] = mb_strlen(substr($namesText, 0, $candidateMatch[1]), 'UTF-8');
        }

        $candidatePositions[] = mb_strlen($namesText, 'UTF-8');

        for ($i = 0, $count = count($candidatePositions) - 1; $i < $count; $i++) {
            $chunk = trim(mb_substr($namesText, $candidatePositions[$i], $candidatePositions[$i + 1] - $candidatePositions[$i], 'UTF-8'));

            if ($chunk === '') {
                continue;
            }

            if (! preg_match('/^(.+?)\s*(?:—|–|\s-\s)\s*(.+)$/u', $chunk, $matches)) {
                continue;
            }

            $namePart = trim($matches[1]);
            $qualifier = null;

            if (preg_match('/^(.*)\s*\(([^)]+)\)\s*$/u', $namePart, $qualifierMatch) === 1) {
                $namePart = trim($qualifierMatch[1]);
                $qualifier = trim($qualifierMatch[2]);
            }

            $rank = trim($matches[2]);
            $rank = trim($rank);

            $rankParts = preg_split('/\s+/u', $rank) ?: [];
            $cleanRankParts = [];

            foreach ($rankParts as $rankPart) {
                if ($cleanRankParts !== [] && preg_match('/^[А-ЯІЇЄҐ]{3,}$/u', $rankPart) === 1) {
                    break;
                }

                $cleanRankParts[] = $rankPart;
            }

            $rank = trim(implode(' ', $cleanRankParts));
            $rank = preg_replace('/\s+(?:а|у)\s+(?:Національній|Державній|Спеціальній|Державній\s+прикордонній).+$/u', '', $rank) ?? $rank;
            $rank = trim($rank);

            $nameTokens = preg_split('/\s+/u', $namePart) ?: [];
            $surname = $nameTokens[0] ?? '';
            $given = $nameTokens[1] ?? '';
            $patronymic = count($nameTokens) > 2 ? implode(' ', array_slice($nameTokens, 2)) : null;
            $rawName = $namePart;
            $normalizedName = $this->normalizer->toNominative($rawName);

            $entries[] = new ParsedAwardee(
                fullName: $normalizedName,
                surname: $surname !== '' ? $surname : null,
                givenName: $given !== '' ? $given : null,
                patronymic: $patronymic,
                rank: $rank !== '' ? $this->normalizeRank($rank) : null,
                award: $this->normalizeAward($award),
                positionNote: $qualifier,
            );
        }

        return $entries;
    }

    private function normalizeRank(string $rank): string
    {
        $rank = trim($rank);

        if ($rank === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $rank) ?: [];

        foreach ($parts as $key => $word) {
            $parts[$key] = implode('-', array_map(
                static function (string $segment): string {
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
                },
                explode('-', $word),
            ));
        }

        return implode(' ', $parts);
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
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/us', $html, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $title !== '' ? $title : null;
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/us', $html, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $title = preg_replace('/\s+[—–-].*$/u', '', $title) ?? $title;

            return $title !== '' ? $title : null;
        }

        return null;
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
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractPublishedAt(string $html, string $text): ?\DateTimeInterface
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

        // Prefer the last Ukrainian date in the document, which is usually
        // the signing date at the bottom of the decree.
        preg_match_all('/(\d{1,2})\s+('.implode('|', array_keys($months)).')\s+(\d{4})/u', $text, $matches, PREG_SET_ORDER);

        if ($matches !== []) {
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

        return null;
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
