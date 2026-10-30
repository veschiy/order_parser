<?php

declare(strict_types=1);

namespace App\Services\Documents;

final readonly class ParsedDecree
{
    /**
     * @param  list<ParsedAwardee>  $awardees
     */
    public function __construct(
        public string $documentUrl,
        public ?string $number = null,
        public ?string $title = null,
        public ?\DateTimeInterface $publishedAt = null,
        public ?string $rawText = null,
        public ?string $rawHtml = null,
        public array $awardees = [],
    ) {}
}
