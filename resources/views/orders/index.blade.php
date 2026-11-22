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

        <!-- Table Filter & Search Controls Bar -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800/80 bg-slate-950/40 px-4 py-2.5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-xs text-slate-400">
                    Відображається: <span id="ordersTable-count" class="font-semibold text-slate-200">0</span>
                </div>
                <button type="button" id="ordersResetFiltersBtn" class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-3 py-1.5 text-sm font-medium text-indigo-300 transition hover:bg-indigo-500/20 hover:text-indigo-200" style="display: none;">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Скинути фільтри
                </button>
            </div>

            <div class="text-xs text-slate-500">
                Натисніть на заголовок або іконку воронки для сортування та фільтрації у стилі Excel
            </div>
        </div>

        <div class="relative">
            <div id="ordersTable-loading" class="hidden absolute inset-0 z-10 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm rounded-xl pointer-events-auto">
                <div class="text-center">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                    <p class="mt-3 text-sm text-slate-300">Завантаження...</p>
                </div>
            </div>

            <div id="ordersTable-empty" class="hidden">
                <p class="text-sm text-slate-400">Поки що жодного указу не розібрано.</p>
            </div>

            <div class="overflow-x-auto overflow-y-visible rounded-xl border border-slate-800">
                <table id="ordersTable" class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900 select-none">
                    <tr>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="0" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <span class="col-title">Номер</span>
                                <div class="flex items-center gap-1">
                                    <span class="sort-icon text-slate-500 text-xs">↕</span>
                                    <button type="button" data-action="sort" class="inline-flex items-center justify-center rounded-md p-0.5 text-slate-400 hover:text-white hover:bg-slate-700 transition" title="Сортувати">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                                    </button>
                                    <button type="button" data-action="filter" class="inline-flex items-center justify-center rounded-md p-0.5 text-slate-400 hover:text-white hover:bg-slate-700 transition" title="Фільтр">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </th>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="1" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">Назва</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
                            </div>
                        </th>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="2" data-type="number">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">Нагороджених</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
                            </div>
                        </th>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="3" data-type="date">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">Дата розбору</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-slate-300">Дія</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                </tbody>
            </table>
            </div>

            <div id="ordersTable-pagination"></div>
        </div>
    </section>
@endsection
