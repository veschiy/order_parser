<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $number
 * @property string|null $title
 * @property string $document_url
 * @property string|null $rss_url
 * @property Carbon|null $published_at
 * @property Carbon $parsed_at
 * @property string|null $raw_text
 * @property-read Collection<int, Awardee> $awardees
 */
#[Fillable([
    'number',
    'title',
    'document_url',
    'rss_url',
    'published_at',
    'parsed_at',
    'raw_text',
])]
final class Order extends Model
{
    use SoftDeletes;

    /**
     * @return HasMany<Awardee, $this>
     */
    public function awardees(): HasMany
    {
        return $this->hasMany(Awardee::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'parsed_at' => 'datetime',
        ];
    }
}
