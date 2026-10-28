<?php

declare(strict_types=1);

namespace App\Services\Rss;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FeedParser
{
    private const string TARGET_DESCRIPTION = 'Про відзначення державними нагородами України';

    /**
     * @return list<FeedItem>
     */
    public function parse(string $url): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("RSS request failed with status {$response->status()}.");
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            throw new RuntimeException('RSS response is not valid XML.');
        }

        $items = [];

        foreach ($xml->channel->item ?? [] as $item) {
            $description = trim((string) ($item->description ?? ''));

            if ($description !== self::TARGET_DESCRIPTION) {
                continue;
            }

            $items[] = new FeedItem(
                title: trim((string) ($item->title ?? '')),
                link: trim((string) ($item->link ?? '')),
                description: $description,
                publishedAt: trim((string) ($item->pubDate ?? '')) ?: null,
            );
        }

        return $items;
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
