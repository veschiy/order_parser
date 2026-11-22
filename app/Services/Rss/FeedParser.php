<?php

declare(strict_types=1);

namespace App\Services\Rss;

use App\Exceptions\RssParseException;
use App\Services\Http\BrowserlikeClient;

final readonly class FeedParser
{
    private BrowserlikeClient $client;

    public function __construct(?BrowserlikeClient $client = null)
    {
        $this->client = $client ?? new BrowserlikeClient;
    }

    private const string TARGET_DESCRIPTION = 'Про відзначення державними нагородами України';

    /**
     * @return list<FeedItem>
     */
    public function parse(string $url): array
    {
        [$status, $body] = $this->request($url);

        if ($status < 200 || $status >= 300) {
            throw new RssParseException("RSS request failed with status {$status}.");
        }

        $xml = simplexml_load_string($body);

        if ($xml === false) {
            throw new RssParseException('RSS response is not valid XML.');
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
