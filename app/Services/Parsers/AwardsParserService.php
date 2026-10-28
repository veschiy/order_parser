<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Models\Awardee;
use App\Models\Order;
use App\Services\Documents\DecreeParser;
use App\Services\Rss\FeedParser;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class AwardsParserService
{
    private FeedParser $feedParser;

    private DecreeParser $decreeParser;

    public function __construct(?FeedParser $feedParser = null, ?DecreeParser $decreeParser = null)
    {
        $this->feedParser = $feedParser ?? new FeedParser;
        $this->decreeParser = $decreeParser ?? new DecreeParser;
    }

    /**
     * @return array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}
     */
    public function parseRss(string $url, ?int $limit = null): array
    {
        $summary = [
            'mode' => 'rss',
            'source_url' => $url,
            'items_found' => 0,
            'items_parsed' => 0,
            'orders_created' => 0,
            'awardees_created' => 0,
            'errors' => [],
        ];

        try {
            $items = $this->feedParser->parse($url);
        } catch (Throwable $throwable) {
            $summary['errors'][] = $throwable->getMessage();

            return $summary;
        }

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        $summary['items_found'] = count($items);

        foreach ($items as $item) {
            if ($item->link === '') {
                $summary['errors'][] = 'RSS item has no document link.';

                continue;
            }

            [$order, $awardeesCreated, $error] = $this->parseAndStoreDocument($item->link);

            if ($error !== null) {
                $summary['errors'][] = $error;

                continue;
            }

            $summary['items_parsed']++;
            $summary['orders_created'] += $order?->wasRecentlyCreated ? 1 : 0;
            $summary['awardees_created'] += $awardeesCreated;
        }

        return $summary;
    }

    /**
     * @return array{mode: string, source_url: string, items_found: int, items_parsed: int, orders_created: int, awardees_created: int, errors: list<string>}
     */
    public function parseSingle(string $url): array
    {
        [$order, $awardeesCreated, $error] = $this->parseAndStoreDocument($url);

        return [
            'mode' => 'single',
            'source_url' => $url,
            'items_found' => 1,
            'items_parsed' => $error === null ? 1 : 0,
            'orders_created' => $order?->wasRecentlyCreated ? 1 : 0,
            'awardees_created' => $awardeesCreated,
            'errors' => $error === null ? [] : [$error],
        ];
    }

    /**
     * @return array{0: ?Order, 1: int, 2: ?string}
     */
    private function parseAndStoreDocument(string $url): array
    {
        try {
            $decree = $this->decreeParser->parse($url);
        } catch (Throwable $throwable) {
            return [null, 0, $throwable->getMessage()];
        }

        $order = null;
        $awardeesCreated = 0;

        try {
            DB::transaction(function () use ($decree, &$order, &$awardeesCreated): void {
                $order = Order::query()->updateOrCreate(
                    ['document_url' => $decree->documentUrl],
                    [
                        'number' => $decree->number,
                        'title' => $decree->title,
                        'rss_url' => null,
                        'published_at' => $decree->publishedAt,
                        'parsed_at' => now(),
                        'raw_text' => $decree->rawText,
                    ]
                );

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

            return [$order, $awardeesCreated, null];
        } catch (Throwable $throwable) {
            return [null, 0, $throwable->getMessage()];
        }
    }
}
