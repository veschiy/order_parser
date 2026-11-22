<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;

final class PaginationResponse
{
    /**
     * Build a pagination array from a Laravel paginator instance.
     *
     * @param  LengthAwarePaginator<int, object>  $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Build a pagination array from manual values (used when pagination is not via a paginator).
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int, from: int, to: int}
     */
    public static function fromValues(int $currentPage, int $total, int $perPage): array
    {
        return [
            'current_page' => $currentPage,
            'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total > 0 ? ($currentPage - 1) * $perPage + 1 : 0,
            'to' => min($currentPage * $perPage, $total),
        ];
    }
}
