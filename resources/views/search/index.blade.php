@extends('layouts.app')

@section('title', 'Пошук нагороджених')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-white">Перевірка нагородження особи</h2>
            <p class="mt-1 text-sm text-slate-400">Введіть ПІБ у називному або пошуковому вигляді, і система покаже знайдені нагороди.</p>
        </div>

        <form method="GET" action="{{ route('search.index') }}" class="flex flex-col gap-3 sm:flex-row">
            <input
                type="search"
                name="q"
                value="{{ $query }}"
                placeholder="Наприклад: Шовковий Олександр"
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 placeholder-slate-500 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
            >
            <button type="submit" class="rounded-xl bg-indigo-500 px-5 py-3 font-medium text-white transition hover:bg-indigo-400">
                Знайти
            </button>
        </form>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Результати пошуку</h3>
            @if ($query !== '')
                <span class="text-sm text-slate-400">Запит: {{ $query }}</span>
            @endif
        </div>

        @if ($query === '')
            <p class="text-sm text-slate-400">Введіть ім’я, щоб перевірити наявність нагород у базі.</p>
        @elseif ($results->isEmpty())
            <p class="text-sm text-rose-300">Нічого не знайдено. Особа може не бути нагородженою, або дані ще не розібрані.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">ПІБ</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Звання/ранг</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Нагорода</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Номер наказу</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($results as $result)
                            <tr class="hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-slate-100">{{ $result->full_name }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $result->rank ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $result->award ?? '—' }}</td>
                                <td class="px-4 py-3 text-indigo-300">{{ $result->order?->number ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
