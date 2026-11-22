@extends('layouts.app')

@section('title', 'Пошук нагороджених')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-white">Перевірка нагородження особи</h2>
            <p class="mt-1 text-sm text-slate-400">Введіть ПІБ у називному або пошуковому вигляді, і система покаже знайдені нагороди.</p>
        </div>

        <form id="searchForm" method="GET" action="{{ route('search.index') }}" class="flex flex-col gap-3 sm:flex-row">
            <label for="searchQuery" class="sr-only">ПІБ</label>
            <input
                id="searchQuery"
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

        <div class="relative">
            <div id="searchResultsTable-loading" class="hidden absolute inset-0 z-10 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm rounded-xl pointer-events-auto">
                <div class="text-center">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                    <p class="mt-3 text-sm text-slate-300">Завантаження...</p>
                </div>
            </div>

            <div id="searchResultsTable-empty" class="hidden">
                <p class="text-sm text-slate-400">Введіть ім'я, щоб перевірити наявність нагород у базі.</p>
            </div>

            <div id="searchResultsTable-empty-msg" class="hidden">
                <p class="text-sm text-rose-300">Нічого не знайдено. Особа може не бути нагородженою, або дані ще не розібрані.</p>
            </div>

            <div class="overflow-x-auto overflow-y-visible rounded-xl border border-slate-800">
                <table id="searchResultsTable" class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900 select-none">
                    <tr>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="0" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <span class="col-title">ПІБ</span>
                            </div>
                        </th>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="1" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <span class="col-title">Звання</span>
                            </div>
                        </th>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="2" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <span class="col-title">Нагорода</span>
                            </div>
                        </th>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="3" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <span class="col-title">Номер указу</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                </tbody>
            </table>
            </div>

            <div id="searchResultsTable-pagination"></div>
        </div>
    </section>
@endsection
