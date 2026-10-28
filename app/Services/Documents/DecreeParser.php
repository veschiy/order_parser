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

    public function parse(string $url): ParsedDecree
    {
        [$status, $body] = $this->request($url);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Decree request failed with status {$status}.");
        }

        $html = $body;
        $text = $this->htmlToText($html);

        return new ParsedDecree(
            documentUrl: $url,
            number: $this->extractNumber($text, $url),
            title: $this->extractTitle($html),
            rawText: mb_substr($text, 0, 100000, 'UTF-8'),
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

            if (! preg_match('/^(.*?)(?=\s+[А-ЯІЇЄҐ][А-ЯІЇЄҐЬ]{2,}\s+[А-ЯІЇЄҐ][а-яіїєґ])/us', $body, $awardMatch)) {
                continue;
            }

            $award = trim($awardMatch[1]);
            $namesText = trim(substr($body, strlen($award)));

            foreach ($this->parseNameEntries($namesText, $award) as $awardee) {
                $awardees[] = $awardee;
            }
        }

        return $awardees;
    }

    /**
     * @return list<ParsedAwardee>
     */
    private function parseNameEntries(string $namesText, string $award): array
    {
        $entries = [];

        preg_match_all('/[А-ЯІЇЄҐ][А-ЯІЇЄҐЬʼ’]{2,}\s+[А-ЯІЇЄҐ][ʼ’а-яіїєґ-]{1,}/u', $namesText, $candidateMatches, PREG_OFFSET_CAPTURE);
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

            if (! preg_match('/^([А-ЯІЇЄҐ][А-ЯІЇЄҐЬʼ’]{2,})\s+([А-ЯІЇЄҐ][а-яіїєґʼ\-\x{2019}\']{2,})(?:\s+([А-ЯІЇЄҐ][а-яіїєґʼ\-\x{2019}\']{2,}))?(?:\s*\(([^)]*)\))?\s*[—–-]\s*(.+)$/us', $chunk, $matches)) {
                continue;
            }

            $surname = $matches[1];
            $given = $matches[2];
            $patronymic = trim($matches[3]) !== '' ? $matches[3] : null;
            $qualifier = trim($matches[4]) !== '' ? trim($matches[4]) : null;
            $rank = trim($matches[5]);

            if (preg_match('/^(.+?)\s+[А-ЯІЇЄҐ]{3,}\s+[А-ЯІЇЄҐ][а-яіїєґ]/u', $rank, $rankMatch)) {
                $rank = trim($rankMatch[1]);
            }

            $rawName = trim($surname.' '.$given.' '.($patronymic ?? ''));
            $normalizedName = $this->normalizer->toNominative($rawName);

            $entries[] = new ParsedAwardee(
                fullName: $normalizedName,
                surname: $surname,
                givenName: $given,
                patronymic: $patronymic,
                rank: $rank !== '' ? $this->normalizeRank($rank) : null,
                award: $award,
                positionNote: $qualifier,
            );
        }

        return $entries;
    }

    private function normalizeRank(string $rank): string
    {
        $rank = trim($rank, " \t\n\r\0\x0B.");

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
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
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
            'Accept-Encoding' => 'gzip, deflate, br',
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
