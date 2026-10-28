<?php

declare(strict_types=1);

namespace App\Services\Documents;

final readonly class ParsedAwardee
{
    public function __construct(
        public string $fullName,
        public ?string $surname = null,
        public ?string $givenName = null,
        public ?string $patronymic = null,
        public ?string $rank = null,
        public ?string $award = null,
        public ?string $positionNote = null,
    ) {}
}
