<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Rss\FeedItem;
use App\Services\Rss\FeedParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FeedParserTest extends TestCase
{
    public function test_rss_fixture_filters_award_documents(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/rss.xml')) ?: '';

        Http::fake([
            'https://example.com/rss' => Http::response($xml, 200),
        ]);

        $items = (new FeedParser)->parse('https://example.com/rss');

        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertSame('Про відзначення державними нагородами України', $item->description);
            $this->assertNotSame('', $item->link);
        }

        $links = array_map(static fn (FeedItem $item): string => $item->link, $items);
        $this->assertContains('https://www.president.gov.ua/documents/1072026-58285', $links);
        $this->assertContains('https://www.president.gov.ua/documents/1062026-58281', $links);
    }
}
