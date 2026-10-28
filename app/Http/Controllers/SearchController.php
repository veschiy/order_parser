<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Awardee;
use Illuminate\Contracts\View\View;
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
                ->limit(100)
                ->get();

        return view('search.index', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
