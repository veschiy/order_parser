<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $full_name
 * @property string|null $surname
 * @property string|null $given_name
 * @property string|null $patronymic
 * @property string|null $rank
 * @property string|null $award
 * @property string|null $position_note
 * @property-read Order|null $order
 */
#[Fillable([
    'order_id',
    'full_name',
    'surname',
    'given_name',
    'patronymic',
    'rank',
    'award',
    'position_note',
])]
final class Awardee extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
