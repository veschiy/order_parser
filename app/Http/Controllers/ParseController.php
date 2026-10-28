<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ParseRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ParseController extends Controller
{
    public function index(): View
    {
        return view('parse.index');
    }

    public function store(ParseRequest $request): RedirectResponse
    {
        $mode = $request->string('mode')->toString();

        if ($mode === 'rss') {
            $url = $request->string('rss_url')->toString();

            return redirect()
                ->route('parse.index')
                ->with('status', "RSS-стрічку «{$url}» поставлено в чергу до реалізації розбору.");
        }

        $url = $request->string('document_url')->toString();

        return redirect()
            ->route('parse.index')
            ->with('status', "Документ «{$url}» поставлено в чергу до реалізації розбору.");
    }
}
