<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim($request->string('q')->toString());

        return view('search.index', [
            'query' => $query,
            'results' => collect(),
        ]);
    }
}
