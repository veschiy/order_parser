@extends('layouts.app')

@section('title', 'Деталі наказу')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6">
            <a href="{{ route('orders.index') }}" class="text-sm text-indigo-300 hover:text-indigo-200">← Назад до списку</a>
            <h2 class="mt-2 text-xl font-semibold text-white">Наказ № {{ $order['number'] ?? $id }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $order['title'] ?? 'Деталі будуть доступні після реалізації зберігання даних.' }}</p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Посилання</div>
                <div class="mt-1 break-all text-sm text-slate-300">{{ $order['document_url'] ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Дата розбору</div>
                <div class="mt-1 text-sm text-slate-300">{{ $order['parsed_at'] ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Нагороджених</div>
                <div class="mt-1 text-sm text-slate-300">{{ count($order['awardees'] ?? []) }}</div>
            </div>
        </div>

        <h3 class="mb-3 text-lg font-semibold text-white">Нагороджені особи</h3>

        @if (empty($order['awardees']))
            <p class="text-sm text-slate-400">Список нагороджених ще не розібрано.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">ПІБ</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Ранг</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Нагорода</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($order['awardees'] as $awardee)
                            <tr class="hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-slate-100">{{ $awardee['name'] ?? '' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $awardee['rank'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $awardee['award'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
