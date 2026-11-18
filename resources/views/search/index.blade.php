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
            <x-excel-table id="searchResultsTable" :total="$results->total()">
                <x-slot:header>
                    <x-excel-table.th col="0" type="text">ПІБ</x-excel-table.th>
                    <x-excel-table.th col="1" type="text">Звання/ранг</x-excel-table.th>
                    <x-excel-table.th col="2" type="text">Нагорода</x-excel-table.th>
                    <x-excel-table.th col="3" type="text">Номер указу</x-excel-table.th>
                </x-slot:header>

                @foreach ($results as $result)
                    <tr class="hover:bg-slate-800/50" data-row="{{ $result->id }}">
                        <td class="px-4 py-3 text-slate-100" data-value="{{ $result->full_name }}">{{ $result->full_name }}</td>
                        <td class="px-4 py-3 text-slate-300" data-value="{{ $result->rank ?? '' }}">{{ $result->rank ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300" data-value="{{ $result->award ?? '' }}">{{ $result->award ?? '—' }}</td>
                        <td class="px-4 py-3 text-indigo-300" data-value="{{ $result->order?->displayNumber() ?? '' }}">{{ $result->order?->displayNumber() ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-excel-table>

            <x-pagination :paginator="$results" />
        @endif
    </section>
@endsection
