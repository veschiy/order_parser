@extends('layouts.app')

@section('title', 'Укази')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Розібрані укази</h2>
                <p class="mt-1 text-sm text-slate-400">Список документов, які вже збережені у базі.</p>
            </div>
            <a href="{{ route('parse.index') }}" class="rounded-xl bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400">
                Розібрати новий
            </a>
        </div>

        @if ($orders->isEmpty())
            <p class="text-sm text-slate-400">Поки що жодного указу не розібрано.</p>
        @else
            <x-excel-table id="ordersTable" :total="$orders->total()">
                <x-slot:header>
                    <x-excel-table.th col="0" type="text">Номер</x-excel-table.th>
                    <x-excel-table.th col="1" type="text">Назва</x-excel-table.th>
                    <x-excel-table.th col="2" type="number">Нагороджених</x-excel-table.th>
                    <x-excel-table.th col="3" type="date">Дата розбору</x-excel-table.th>
                    <th class="px-4 py-3 text-left font-medium text-slate-300">Дія</th>
                </x-slot:header>

                @foreach ($orders as $order)
                    <tr class="hover:bg-slate-800/50 {{ $order->trashed() ? 'opacity-50' : '' }}" data-row="{{ $order->id }}">
                        <td class="px-4 py-3 text-indigo-300 {{ $order->trashed() ? 'line-through' : '' }}" data-value="{{ $order->displayNumber() }}">{{ $order->displayNumber() }}</td>
                        <td class="px-4 py-3 text-slate-100 {{ $order->trashed() ? 'line-through' : '' }}" data-value="{{ $order->title ?? '' }}">{{ $order->title ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300" data-value="{{ $order->awardees_count ?? 0 }}">{{ $order->awardees_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-slate-300" data-value="{{ optional($order->parsed_at)->format('Y-m-d H:i:s') ?? '' }}">{{ optional($order->parsed_at)->toDateTimeString() ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('orders.show', $order->id) }}" class="text-indigo-300 hover:text-indigo-200">Деталі</a>
                        </td>
                    </tr>
                @endforeach
            </x-excel-table>

            <x-pagination :paginator="$orders" />
        @endif
    </section>
@endsection
