<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class OrderController extends Controller
{
    public function index(): View
    {
        return view('orders.index', [
            'orders' => collect(),
        ]);
    }

    public function show(int $id): View
    {
        return view('orders.show', [
            'id' => $id,
            'order' => [],
        ]);
    }
}
