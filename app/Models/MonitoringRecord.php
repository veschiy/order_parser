<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $sheet
 * @property string $full_name
 * @property string|null $tax_id
 * @property CarbonInterface|null $submission_date
 * @property string|null $rank
 * @property string|null $award
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
#[Fillable([
    'sheet',
    'full_name',
    'tax_id',
    'submission_date',
    'rank',
    'award',
])]
final class MonitoringRecord extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
        ];
    }
}
