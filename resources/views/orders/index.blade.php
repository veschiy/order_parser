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

        <div id="ordersTable-empty" class="hidden">
            <p class="text-sm text-slate-400">Поки що жодного указу не розібрано.</p>
        </div>

        <div id="ordersTable-loading" class="hidden py-12 text-center">
            <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
            <p class="mt-3 text-sm text-slate-400">Завантаження...</p>
        </div>

        <div class="overflow-x-auto overflow-y-visible rounded-xl border border-slate-800">
            <table id="ordersTable" class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900 select-none">
                    <tr>
                        <th class="relative px-3 py-3 text-left font-medium text-slate-300" data-col-index="0" data-type="text">
                            <div class="flex items-center text-left justify-between gap-1">
                                <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                                    <span class="col-title">Номер</span>
                                    <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
                                </button>
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
    </section>
@endsection
