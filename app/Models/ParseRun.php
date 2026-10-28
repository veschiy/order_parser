<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $mode
 * @property string $source_url
 * @property string $status
 * @property int $items_found
 * @property int $items_parsed
 * @property int $orders_created
 * @property int $awardees_created
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable([
    'mode',
    'source_url',
    'status',
    'items_found',
    'items_parsed',
    'orders_created',
    'awardees_created',
    'error',
    'started_at',
    'finished_at',
])]
final class ParseRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
