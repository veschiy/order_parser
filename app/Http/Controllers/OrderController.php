<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'paginator' => $this->paginateAwardees(
                $order,
                is_string(request('page')) ? (int) request('page') : 1,
            ),
        ]);
    }

    /**
     * Slice an order's awardees into a paginated view for the detail page.
     * The full collection on $order->awardees is left untouched so the total
     * count badge still reflects every awardee, not just the current page.
     */
    /** @return LengthAwarePaginator<int, mixed> */
    protected function paginateAwardees(Order $order, int $page): LengthAwarePaginator
    {
        $perPage = 25;
        $page = max(1, $page);
        $items = collect($order->awardees)->sortBy('id')->forPage($page, $perPage)->values();

        return (new LengthAwarePaginator($items, $order->awardees->count(), $perPage, $page))
            ->withPath(route('orders.show', $order->id));
    }

    public function export(int $id): StreamedResponse
    {
        $order = Order::withTrashed()
            ->with('awardees')
            ->findOrFail($id);

        $safeNumber = preg_replace('/[\/\\\\\x00-\x1f\x7f%]/', '-', (string) ($order->number ?? $order->id));
        $filename = 'order-'.(($safeNumber === null || $safeNumber === '') ? (string) $order->id : $safeNumber).'.csv';

        $callback = function () use ($order): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            try {
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
            } finally {
                fclose($handle);
            }
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

    public function indexJson(Request $request): JsonResponse
    {
        $query = Order::withTrashed()->withCount('awardees');

        if ($request->boolean('withTrashed', true) === false) {
            $query->whereNull('deleted_at');
        }

        $sort = $request->string('sort')->toString() ?: 'parsed_at';
        $direction = $request->string('direction')->toString() ?: 'desc';

        $validSort = ['number', 'title', 'awardees_count', 'parsed_at'];
        if (in_array($sort, $validSort, true)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $orders = $query->paginate($request->integer('perPage', 20));

        return response()->json([
            'data' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->displayNumber(),
                'title' => $order->title ?? '—',
                'awardees_count' => $order->awardees_count ?? 0,
                'parsed_at' => $order->parsed_at->toDateTimeString(),
                'parsed_at_sort' => $order->parsed_at->format('Y-m-d H:i:s'),
                'trashed' => (bool) $order->trashed(),
                'order_id' => $order->id,
            ]),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    public function showAwardeesJson(int $id, Request $request): JsonResponse
    {
        $order = Order::withTrashed()->with('awardees')->findOrFail($id);

        $perPage = $request->integer('perPage', 25);
        $page = max(1, $request->integer('page', 1));

        $sort = $request->string('sort')->toString() ?: 'id';
        $direction = $request->string('direction')->toString() ?: 'asc';

        $awardees = collect($order->awardees)->sortBy(fn ($awardee) => match ($sort) {
            'full_name' => $awardee->full_name,
            'rank' => $awardee->rank ?? '',
            'award' => $awardee->award ?? '',
            default => $awardee->id,
        }, $direction === 'desc' ? 3 : 0)->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator($awardees, $order->awardees->count(), $perPage, $page);

        return response()->json([
            'data' => $awardees->map(fn ($awardee): array => [
                'id' => $awardee->id,
                'full_name' => $awardee->full_name,
                'rank' => $awardee->rank ?? '—',
                'award' => $awardee->award ?? '—',
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
