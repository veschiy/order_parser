<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Models\Awardee;
use App\Models\Order;
use App\Models\ParseRun;
use App\Services\Documents\DecreeParser;
use App\Services\Documents\ParsedDecree;
use App\Services\Http\BrowserlikeClient;
use App\Services\Rss\FeedItem;
use App\Services\Rss\FeedParser;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AwardsParserService
{
    private readonly FeedParser $feedParser;

    private readonly DecreeParser $decreeParser;

    private readonly BrowserlikeClient $client;

    public function __construct(?FeedParser $feedParser = null, ?DecreeParser $decreeParser = null, ?BrowserlikeClient $client = null)
    {
        $this->client = $client ?? new BrowserlikeClient;
        $this->feedParser = $feedParser ?? new FeedParser($this->client);
        $this->decreeParser = $decreeParser ?? new DecreeParser(null, $this->client);
    }

    /**
     * @return array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}
     */
    public function parseRss(string $url, ?int $limit = null): array
    {
        $summary = $this->createSummary('rss', $url);
        $run = $this->createParseRun('rss', $url);

        $items = $this->fetchRssItems($url, $summary);
        if ($items === null) {
            $this->finishParseRun($run, $summary, 'failed');

            return $summary;
        }

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        $summary['items_found'] = count($items);

        foreach ($items as $item) {
            $this->processRssItem($item, $summary);
        }

        $status = $summary['errors'] === [] ? 'completed' : 'completed_with_errors';
        $this->finishParseRun($run, $summary, $status);

        return $summary;
    }

    /**
     * @return array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}
     */
    private function createSummary(string $mode, string $url): array
    {
        return [
            'mode' => $mode,
            'source_url' => $url,
            'items_found' => 0,
            'items_parsed' => 0,
            'orders_created' => 0,
            'awardees_created' => 0,
            'errors' => [],
        ];
    }

    private function createParseRun(string $mode, string $url): ParseRun
    {
        return ParseRun::query()->create([
            'mode' => $mode,
            'source_url' => $url,
            'status' => 'running',
            'items_found' => 0,
            'items_parsed' => 0,
            'orders_created' => 0,
            'awardees_created' => 0,
            'started_at' => now(),
        ]);
    }

    /**
     * @param array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}& $summary
     * @return list<FeedItem>|null
     */
    private function fetchRssItems(string $url, array &$summary): ?array
    {
        try {
            return $this->feedParser->parse($url);
        } catch (Throwable $throwable) {
            $summary['errors'][] = $throwable->getMessage();

            return null;
        }
    }

    /**
     * @param array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}& $summary
     */
    private function processRssItem(FeedItem $item, array &$summary): void
    {
        if ($item->link === '') {
            $summary['errors'][] = 'RSS item has no document link.';

            return;
        }

        [$order, $awardeesCreated, $error] = $this->parseAndStoreDocument($item->link, $item->publishedAt);

        if ($error !== null) {
            $summary['errors'][] = $error;

            return;
        }

        $summary['items_parsed']++;
        $summary['orders_created'] += $order?->wasRecentlyCreated ? 1 : 0;
        $summary['awardees_created'] += $awardeesCreated;
    }

    /**
     * @param  array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}  $summary
     */
    private function finishParseRun(ParseRun $run, array $summary, string $status): void
    {
        $run->forceFill([
            'items_found' => $summary['items_found'],
            'items_parsed' => $summary['items_parsed'],
            'orders_created' => $summary['orders_created'],
            'awardees_created' => $summary['awardees_created'],
            'status' => $status,
            'error' => $summary['errors'] === [] ? null : implode('; ', $summary['errors']),
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @return array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}
     */
    public function parseSingle(string $url): array
    {
        $run = ParseRun::query()->create([
            'mode' => 'single',
            'source_url' => $url,
            'status' => 'running',
            'items_found' => 1,
            'items_parsed' => 0,
            'orders_created' => 0,
            'awardees_created' => 0,
            'started_at' => now(),
        ]);

        [$order, $awardeesCreated, $error] = $this->parseAndStoreDocument($url);

        $summary = [
            'mode' => 'single',
            'source_url' => $url,
            'items_found' => 1,
            'items_parsed' => $error === null ? 1 : 0,
            'orders_created' => $order?->wasRecentlyCreated ? 1 : 0,
            'awardees_created' => $awardeesCreated,
            'errors' => $error === null ? [] : [$error],
        ];

        $run->forceFill([
            'items_parsed' => $summary['items_parsed'],
            'orders_created' => $summary['orders_created'],
            'awardees_created' => $summary['awardees_created'],
            'status' => $error === null ? 'completed' : 'completed_with_errors',
            'error' => $error,
            'finished_at' => now(),
        ])->save();

        return $summary;
    }

    /**
     * @return array{0: ?Order, 1: int, 2: ?string}
     */
    private function parseAndStoreDocument(string $url, ?string $publishedAt = null): array
    {
        $publicationDate = $this->parsePublishedAt($publishedAt);
        $existingOrder = Order::withTrashed()->where('document_url', $url)->first();

        if ($existingOrder !== null && $existingOrder->awardees()->exists()) {
            if ($existingOrder->trashed()) {
                $existingOrder->restore();
            }

            return [$existingOrder, 0, null];
        }

        $decree = $this->fetchDecree($url, $publicationDate);
        if (! $decree instanceof ParsedDecree) {
            return [null, 0, $this->lastError];
        }

        return $this->storeDecree($decree);
    }

    private string $lastError = '';

    private function parsePublishedAt(?string $publishedAt): ?\DateTimeImmutable
    {
        if ($publishedAt === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($publishedAt);
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchDecree(string $url, ?\DateTimeImmutable $publicationDate): ?ParsedDecree
    {
        $this->lastError = '';

        try {
            return $this->decreeParser->parse($url, $publicationDate);
        } catch (Throwable $throwable) {
            $this->lastError = $throwable->getMessage();

            return null;
        }
    }

    /**
     * @return array{0: ?Order, 1: int, 2: ?string}
     */
    private function storeDecree(ParsedDecree $decree): array
    {
        $order = null;
        $awardeesCreated = 0;

        try {
            DB::transaction(function () use ($decree, &$order, &$awardeesCreated): void {
                $order = Order::withTrashed()->updateOrCreate(
                    ['document_url' => $decree->documentUrl],
                    [
                        'number' => $decree->number,
                        'title' => $decree->title,
                        'rss_url' => null,
                        'published_at' => $decree->publishedAt,
                        'parsed_at' => now(),
                        'raw_text' => $decree->rawText,
                        'raw_html' => $decree->rawHtml,
                    ]
                );

                if ($order->trashed()) {
                    $order->restore();
                }

                foreach ($decree->awardees as $awardee) {
                    $created = Awardee::query()->firstOrCreate(
                        [
                            'order_id' => $order->id,
                            'full_name' => $awardee->fullName,
                            'award' => $awardee->award,
                        ],
                        [
                            'surname' => $awardee->surname,
                            'given_name' => $awardee->givenName,
                            'patronymic' => $awardee->patronymic,
                            'rank' => $awardee->rank,
                            'position_note' => $awardee->positionNote,
                        ]
                    );

                    if ($created->wasRecentlyCreated) {
                        $awardeesCreated++;
                    }
                }
            });
        } catch (Throwable $throwable) {
            return [null, 0, $throwable->getMessage()];
        }

        return [$order, $awardeesCreated, null];
    }
}
