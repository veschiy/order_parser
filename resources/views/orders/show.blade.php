@extends('layouts.app')

@section('title', 'Деталі указу')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6">
            <a href="{{ route('orders.index') }}" class="text-sm text-indigo-300 hover:text-indigo-200">← Назад до списку</a>
            <h2 class="mt-2 text-xl font-semibold text-white">Указ {{ $order->displayNumber() }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $order->title ?? 'Деталі будуть доступні після реалізації зберігання даних.' }}</p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Посилання</div>
                <div class="mt-1 break-all text-sm text-slate-300">{{ $order->document_url ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Дата розбору</div>
                <div class="mt-1 text-sm text-slate-300">{{ $order->parsed_at?->toDateTimeString() ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Нагороджених</div>
                <div class="mt-1 text-sm text-slate-300">{{ $order->awardees->count() }}</div>
            </div>
        </div>

        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-white">Нагороджені особи</h3>
            <div class="flex gap-2">
                <a href="{{ route('orders.export', $order->id) }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-400">Експорт у CSV</a>
                @if ($order->trashed())
                    <form method="POST" action="{{ route('orders.restore', $order->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-400">Відновити</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('orders.destroy', $order->id) }}" data-confirm="Видалити указ і приховати його з пошуку?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-500">Видалити</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="relative">
            <div id="awardeesTable-loading" class="hidden absolute inset-0 z-10 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm rounded-xl pointer-events-auto">
                <div class="text-center">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                    <p class="mt-3 text-sm text-slate-300">Завантаження...</p>
                </div>
            </div>

            <div id="awardeesTable-empty" class="hidden">
                <p class="text-sm text-slate-400">Список нагороджених ще не розібрано.</p>
            </div>

            <div class="overflow-x-auto">
                <table id="awardeesTable" data-order-id="{{ $order->id }}" class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-300" data-col-index="0" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">ПІБ</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-300" data-col-index="1" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">Звання</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-300" data-col-index="2" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">Нагорода</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                </tbody>
            </table>
            </div>

            <div id="awardeesTable-pagination"></div>
        </div>
    </section>
@endsection
