<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Awardee;
use App\Services\PaginationResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim($request->string('q')->toString());

        $results = $query === ''
            ? collect()
            : Awardee::query()
                ->with('order')
                ->whereHas('order', function ($builder): void {
                    $builder->whereNull('deleted_at');
                })
                ->where(function ($builder) use ($query): void {
                    $pattern = '%'.mb_strtolower($query, 'UTF-8').'%';

                    $builder
                        ->whereRaw('mb_lower(full_name) like ?', [$pattern])
                        ->orWhereRaw("mb_lower(coalesce(surname, '')) like ?", [$pattern])
                        ->orWhereRaw("mb_lower(coalesce(given_name, '')) like ?", [$pattern]);
                })
                ->orderBy('full_name')
                ->paginate(15)
                ->appends(['q' => $query]);

        return view('search.index', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function indexJson(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $perPage = $request->integer('perPage', 15);
        $page = max(1, $request->integer('page', 1));

        if ($query === '') {
            return response()->json([
                'data' => [],
                'pagination' => PaginationResponse::fromValues(1, 0, $perPage),
            ]);
        }

        $results = Awardee::query()
            ->with('order')
            ->whereHas('order', function ($builder): void {
                $builder->whereNull('deleted_at');
            })
            ->where(function ($builder) use ($query): void {
                $pattern = '%'.mb_strtolower($query, 'UTF-8').'%';
                $builder
                    ->whereRaw('mb_lower(full_name) like ?', [$pattern])
                    ->orWhereRaw("mb_lower(coalesce(surname, '')) like ?", [$pattern])
                    ->orWhereRaw("mb_lower(coalesce(given_name, '')) like ?", [$pattern]);
            })
            ->orderBy('full_name')
            ->paginate($perPage, ['*'], 'page', $page);

        /** @var LengthAwarePaginator<int, Awardee> $results */
        return response()->json([
            'data' => $results->map(fn ($result): array => [
                'id' => $result->id,
                'order_id' => $result->order_id,
                'full_name' => $result->full_name,
                'rank' => $result->rank ?? '—',
                'award' => $result->award ?? '—',
                'order_display' => $result->order?->displayNumber() ?? '—',
            ]),
            'pagination' => PaginationResponse::fromPaginator($results),
        ]);
    }
}
