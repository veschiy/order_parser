<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

final class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->withCount('awardees')
            ->latest()
            ->paginate(20);

        return view('orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(int $id): View
    {
        $order = Order::query()
            ->with('awardees')
            ->findOrFail($id);

        return view('orders.show', [
            'order' => $order,
        ]);
    }
}
