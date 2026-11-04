<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::withTrashed()
            ->withCount('awardees')
            ->latest()
            ->paginate(20);

        return view('orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(int $id): View
    {
        $order = Order::withTrashed()
            ->with('awardees')
            ->findOrFail($id);

        return view('orders.show', [
            'order' => $order,
        ]);
    }

    public function export(int $id): StreamedResponse
    {
        $order = Order::withTrashed()
            ->with('awardees')
            ->findOrFail($id);

        $filename = 'order-'.($order->number ?? $order->id).'.csv';

        $callback = function () use ($order): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ПІБ', 'Звання', 'Нагорода', 'Номер указу'], ';');

            foreach ($order->awardees as $awardee) {
                fputcsv($handle, [
                    $awardee->full_name,
                    $awardee->rank ?? '',
                    $awardee->award ?? '',
                    $order->displayNumber(),
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $order = Order::withTrashed()->findOrFail($id);
        $order->delete();

        return redirect()->route('orders.index')->with('status', 'Указ позначено як видалений.');
    }

    public function restore(int $id): RedirectResponse
    {
        $order = Order::withTrashed()->findOrFail($id);
        $order->restore();

        return redirect()->route('orders.show', $order->id)->with('status', 'Указ відновлено.');
    }
}
