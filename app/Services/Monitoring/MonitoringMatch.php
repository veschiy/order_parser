<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\Awardee;

final readonly class MonitoringMatch
{
    public function __construct(
        public Awardee $awardee,
        public bool $nameMatched,
        public bool $rankMatched,
        public bool $awardMatched,
        public bool $dateMatched,
        public int $score,
    ) {}
}
