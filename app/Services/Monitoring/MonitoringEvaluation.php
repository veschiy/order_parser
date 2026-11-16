<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MonitoringRecord;

final readonly class MonitoringEvaluation
{
    /**
     * @param  list<MonitoringMatch>  $matches
     */
    public function __construct(
        public MonitoringRecord $record,
        public int $score,
        public array $matches = [],
        public ?MonitoringMatch $bestMatch = null,
    ) {}
}
