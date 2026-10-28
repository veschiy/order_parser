<?php

declare(strict_types=1);

namespace App\Services\Rss;

final readonly class FeedItem
{
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public ?string $publishedAt,
    ) {}
}
