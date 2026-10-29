@extends('layouts.app')

@section('title', 'Накази')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Розібрані накази</h2>
                <p class="mt-1 text-sm text-slate-400">Список документов, які вже збережені у базі.</p>
            </div>
            <a href="{{ route('parse.index') }}" class="rounded-xl bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400">
                Розібрати новий
            </a>
        </div>

        @if ($orders->isEmpty())
            <p class="text-sm text-slate-400">Поки що жодного наказу не розібрано.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Номер</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Назва</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Нагороджених</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Дата розбору</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Дія</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-slate-800/50 {{ $order->trashed() ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3 text-indigo-300 {{ $order->trashed() ? 'line-through' : '' }}">{{ $order->displayNumber() }}</td>
                                <td class="px-4 py-3 text-slate-100 {{ $order->trashed() ? 'line-through' : '' }}">{{ $order->title ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $order->awardees_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ optional($order->parsed_at)->toDateTimeString() ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('orders.show', $order->id) }}" class="text-indigo-300 hover:text-indigo-200">Деталі</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
