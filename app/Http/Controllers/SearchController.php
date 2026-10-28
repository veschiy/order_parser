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
                ->where(function ($builder) use ($query): void {
                    $builder
                        ->where('full_name', 'like', '%'.$query.'%')
                        ->orWhere('surname', 'like', '%'.$query.'%')
                        ->orWhere('given_name', 'like', '%'.$query.'%');
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
