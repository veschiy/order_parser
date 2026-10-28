<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Services\Names\UkrainianNameNormalizer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class DecreeParser
{
    private UkrainianNameNormalizer $normalizer;

    public function __construct(?UkrainianNameNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new UkrainianNameNormalizer;
    }

    public function parse(string $url): ParsedDecree
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Decree request failed with status {$response->status()}.");
        }

        $html = $response->body();
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
        $offset = 0;

        while (($position = mb_stripos($text, 'Нагородити', $offset, 'UTF-8')) !== false) {
            $next = mb_stripos($text, 'Нагородити', $position + 1, 'UTF-8');
            $segment = $next === false
                ? mb_substr($text, $position, null, 'UTF-8')
                : mb_substr($text, $position, $next - $position, 'UTF-8');

            $awardee = $this->parseSegment($segment);

            if ($awardee instanceof ParsedAwardee) {
                $awardees[] = $awardee;
            }

            $offset = $next === false ? mb_strlen($text, 'UTF-8') : $next;
        }

        return $awardees;
    }

    private function parseSegment(string $segment): ?ParsedAwardee
    {
        if (! preg_match('/([А-ЯІЇЄҐ][А-ЯІЇЄҐЬ]{2,})\s+([А-ЯІЇЄҐ][а-яіїєґ\'-]{2,})(?:\s+([А-ЯІЇЄҐ][а-яіїєґ\'-]{2,}))?/u', $segment, $matches)) {
            return null;
        }

        $surname = $matches[1];
        $given = $matches[2];
        $patronymic = $matches[3] ?? null;

        $rawName = trim($surname.' '.$given.' '.($patronymic ?? ''));
        $normalizedName = $this->normalizer->toNominative($rawName);

        $award = null;
        if (preg_match('/^Нагородити\s+(.+?)\s+[А-ЯІЇЄҐ]{3,}/us', $segment, $awardMatch)) {
            $award = trim($awardMatch[1]);
        }

        $positionNote = null;
        $partsByDash = preg_split('/[–-]/u', $segment);

        if (is_array($partsByDash) && count($partsByDash) > 1) {
            $positionNote = trim($partsByDash[1]);
        }

        return new ParsedAwardee(
            fullName: $normalizedName,
            surname: $surname,
            givenName: $given,
            patronymic: $patronymic,
            rank: null,
            award: $award,
            positionNote: $positionNote,
        );
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
        if (preg_match('/<title[^>]*>(.*?)<\/title>/us', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null;
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/us', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null;
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
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'uk,uk-UA;q=0.9,en;q=0.8',
        ];
    }
}
