<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Awardee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $perPage = (int) $request->input('perPage', 15);
        $page = max(1, (int) $request->input('page', 1));

        if ($query === '') {
            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
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

        return response()->json([
            'data' => $results->map(fn ($result) => [
                'id' => $result->id,
                'full_name' => $result->full_name,
                'rank' => $result->rank ?? '—',
                'award' => $result->award ?? '—',
                'order_display' => $result->order?->displayNumber() ?? '—',
            ]),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }
}
