@extends('layouts.app')

@section('title', 'Моніторинг нагороджень')

@section('content')
    <div class="space-y-6">
        <!-- Top Action & Input Forms -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Manual Entry Form -->
            <section class="relative rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold text-white">Додати особу до моніторингу</h2>
                    <p class="mt-1 text-sm text-slate-400">Введіть дані вручну для перевірки наявності в указах Президента.</p>
                </div>

                <form method="POST" action="{{ route('monitoring.store') }}" class="space-y-4" onsubmit="document.getElementById('monitoringStore-loading').classList.remove('hidden')">
                    @csrf
                    <div>
                        <label for="full_name" class="mb-1 block text-sm font-medium text-slate-300">ПІБ <span class="text-rose-400">*</span></label>
                        <input
                            id="full_name"
                            type="text"
                            name="full_name"
                            required
                            value="{{ old('full_name') }}"
                            placeholder="Шовковий Олександр Володимирович"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                        >
                    </div>

                    <div class="grid gap-x-4 gap-y-6 sm:grid-cols-2 lg:grid-cols-2">
                        <div>
                            <label for="sheet" class="mb-1.5 block text-sm font-medium text-slate-300">Аркуш</label>
                            <input
                                id="sheet"
                                type="text"
                                name="sheet"
                                value="{{ old('sheet') }}"
                                placeholder="1, Арк. 2 або 12/3"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 placeholder-slate-500 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                            >
                        </div>
                        <div>
                            <label for="submission_date" class="mb-1.5 block text-sm font-medium text-slate-300">Дата подання</label>
                            <input
                                id="submission_date"
                                type="date"
                                name="submission_date"
                                value="{{ old('submission_date') }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                            >
                        </div>
                        <div>
                            <label for="rank" class="mb-1.5 block text-sm font-medium text-slate-300">Звання</label>
                            <input
                                id="rank"
                                type="text"
                                name="rank"
                                value="{{ old('rank') }}"
                                placeholder="генерал, полковник..."
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 placeholder-slate-500 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                            >
                        </div>
                        <div>
                            <label for="award" class="mb-1.5 block text-sm font-medium text-slate-300">Нагорода</label>
                            <input
                                id="award"
                                type="text"
                                name="award"
                                value="{{ old('award') }}"
                                placeholder="орден «За мужність» III ступеня"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 placeholder-slate-500 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                            >
                        </div>
                    </div>

                    <button type="submit" class="rounded-xl bg-indigo-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-400">
                        Додати запис
                    </button>
                </form>
                <div id="monitoringStore-loading" class="hidden absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-slate-900/60 backdrop-blur-sm pointer-events-auto">
                    <div class="text-center">
                        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                        <p class="mt-3 text-sm text-slate-300">Завантаження...</p>
                    </div>
                </div>
            </section>

            <!-- CSV Import Form -->
            <section class="relative rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold text-white">Імпорт з CSV</h2>
                    <p class="mt-1 text-sm text-slate-400">Завантажте CSV-файл із переліком осіб для масового моніторингу.</p>
                </div>

                <form method="POST" action="{{ route('monitoring.import') }}" enctype="multipart/form-data" class="space-y-4" onsubmit="document.getElementById('monitoringImport-loading').classList.remove('hidden')">
                    @csrf
                    <div>
                        <label for="csv_file" class="mb-1 block text-sm font-medium text-slate-300">Виберіть CSV файл</label>
                        <input
                            id="csv_file"
                            type="file"
                            name="csv_file"
                            accept=".csv,.txt"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-300 hover:file:bg-slate-700"
                        >
                    </div>

                    <div class="flex items-start gap-2 rounded-xl border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-300">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="leading-relaxed">
                            Підтримуються колонки: <span class="font-medium text-slate-200">Аркуш</span>, <span class="font-medium text-slate-200">ПІБ</span>, <span class="font-medium text-slate-200">Звання</span>, <span class="font-medium text-slate-200">Дата подання</span>, <span class="font-medium text-slate-200">Нагорода</span>. Роздільники: кома, крапка з комою або таб.
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500">
                            Імпортувати CSV
                        </button>
                    </div>
                </form>
                <div id="monitoringImport-loading" class="hidden absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-slate-900/60 backdrop-blur-sm pointer-events-auto">
                    <div class="text-center">
                        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                        <p class="mt-3 text-sm text-slate-300">Завантаження...</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Monitoring Table Section -->
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-white">Таблиця моніторингу</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Порівняння подань із розібраними указами Президента (усього записів: {{ $totalCount }}).
                    </p>
                </div>

                @if ($totalCount > 0)
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('monitoring.export', $search !== '' ? ['q' => $search] : []) }}" data-monitoring-export data-token="{{ csrf_token() }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800/80 px-3.5 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white">
                            <span data-export-label class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Експорт у CSV
                            </span>
                            <span data-export-loading class="hidden items-center gap-1.5" role="status">
                                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-400 border-t-transparent"></span>
                                Формування CSV...
                            </span>
                        </a>
                        <form method="POST" action="{{ route('monitoring.clear') }}" data-confirm="Видалити всі записи моніторингу?" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-900/60 bg-rose-950/40 px-3.5 py-2 text-sm font-medium text-rose-300 transition hover:bg-rose-900/60 hover:text-rose-100">
                                <svg class="h-4 w-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Очистити таблицю
                            </button>
                        </form>
                    </div>
                    <p data-monitoring-export-status class="mt-2 hidden text-sm text-rose-300" role="status" aria-live="polite"></p>
                @endif
            </div>

            <!-- Table Filter & Search Controls Bar -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800/80 bg-slate-950/40 px-4 py-2.5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-xs text-slate-400">
                        Відображається: <span id="monitoringTable-count" class="font-semibold text-slate-200">0</span> із {{ $totalCount }}
                    </div>
                    <button type="button" id="monitoringResetFiltersBtn" class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-3 py-1.5 text-sm font-medium text-indigo-300 transition hover:bg-indigo-500/20 hover:text-indigo-200" style="display: none;">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Скинути фільтри
                    </button>
                </div>

                <form id="monitoringSearchForm" class="flex items-center gap-2">
                    <label for="monitoringSearchInput" class="sr-only">Швидкий пошук</label>
                    <input
                        type="search"
                        name="q"
                        id="monitoringSearchInput"
                        value="{{ $search }}"
                        placeholder="Швидкий пошук..."
                        class="w-48 sm:w-64 rounded-xl border border-slate-700 bg-slate-900 px-3 py-1.5 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                    <button type="submit" class="rounded-xl bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-slate-700 transition">
                        Пошук
                    </button>
                    <a href="#" id="monitoringSearchReset" class="rounded-xl border border-slate-700 px-3 py-1.5 text-sm text-slate-400 hover:text-white transition {{ $search !== '' ? '' : 'hidden' }}">
                        Скинути
                    </a>
                </form>
            </div>

            <div class="relative">
                <div id="monitoringTable-loading" class="hidden absolute inset-0 z-10 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm rounded-xl pointer-events-auto">
                    <div class="text-center">
                        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                        <p class="mt-3 text-sm text-slate-300">Завантаження...</p>
                    </div>
                </div>

                <div id="monitoringTable-empty" class="hidden">
                    <p class="text-sm text-slate-400">
                        @if ($search !== '')
                            За запитом «{{ $search }}» нічого не знайдено.
                        @else
                            Записів у моніторингу поки немає. Додайте особу вручну або імпортуйте CSV файл.
                        @endif
                    </p>
                </div>

                <div class="overflow-x-auto overflow-y-visible rounded-xl border border-slate-800">
                    <table id="monitoringTable" class="min-w-full divide-y divide-slate-800 text-xs">
                    <thead class="bg-slate-900 select-none">
                        <tr>
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="0" data-type="text">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">Аркуш</span>
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
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="1" data-type="text">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">Звання</span>
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
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="2" data-type="text">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">ПІБ</span>
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
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="3" data-type="date">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">Дата подання</span>
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
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="4" data-type="text">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">Нагорода</span>
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
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="5" data-type="number">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">Бал</span>
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
                            <th class="relative px-1.5 py-2 text-left font-bold text-slate-300" data-col-index="6" data-type="text">
                                <div class="flex items-center text-left justify-between gap-1">
                                    <span class="col-title">Збіги в указах</span>
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
                            <th class="px-1.5 py-2 text-left font-bold text-slate-300">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                    </tbody>
                </table>
            </div>

                <div id="monitoringTable-pagination"></div>
            </div>
        </section>

        <!-- Scoring Rules Section -->
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
            <h3 class="text-lg font-semibold text-white">Правила розрахунку колонки «Бал»</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 text-sm text-slate-300">
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex items-center gap-2 font-semibold text-indigo-300">
                        <span class="rounded-full bg-indigo-950 px-2 py-0.5 text-xs border border-indigo-700/60">+1 бал</span>
                        <span>Збіг за ПІБ</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Якщо ПІБ особи знайдено у списку нагороджених в указах Президента. Якщо ПІБ не збігається, бал дорівнює 0.
                    </p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex items-center gap-2 font-semibold text-purple-300">
                        <span class="rounded-full bg-purple-950 px-2 py-0.5 text-xs border border-purple-700/60">+1 бал</span>
                        <span>Збіг за Званням</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Якщо введено «Звання» і він збігається з званням нагородженої особи в указі. Додає +1 бал (до 2).
                    </p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex items-center gap-2 font-semibold text-emerald-300">
                        <span class="rounded-full bg-emerald-950 px-2 py-0.5 text-xs border border-emerald-700/60">+1 бал</span>
                        <span>Збіг за Нагородою</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Якщо окрім збігу імені (та звання) також збігається назва нагороди, додається ще +1 бал.
                    </p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex items-center gap-2 font-semibold text-blue-300">
                        <span class="rounded-full bg-blue-950 px-2 py-0.5 text-xs border border-blue-700/60">+1 бал</span>
                        <span>Дата указу після подання, не пізніше 6 місяців</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Якщо дата публікації знайденого указу є пізнішою за вказану «Дату подання» та не перевищує 6 місяців після неї, додається ще +1 бал (максимум — 4 бали).
                    </p>
                </div>
            </div>
        </section>
    </div>

    <!-- Edit Modal Dialog -->
    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Редагування запису моніторингу</h3>
                <button type="button" data-close-modal="editModal" class="text-slate-400 hover:text-white">&times;</button>
            </div>

            <form id="editForm" method="POST" action="" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="edit_full_name" class="mb-1 block text-sm font-medium text-slate-300">ПІБ <span class="text-rose-400">*</span></label>
                    <input
                        id="edit_full_name"
                        type="text"
                        name="full_name"
                        required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label for="edit_sheet" class="mb-1 block text-sm font-medium text-slate-300">Аркуш</label>
                    <input
                        id="edit_sheet"
                        type="text"
                        name="sheet"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="edit_submission_date" class="mb-1 block text-sm font-medium text-slate-300">Дата подання</label>
                        <input
                            id="edit_submission_date"
                            type="date"
                            name="submission_date"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label for="edit_rank" class="mb-1 block text-sm font-medium text-slate-300">Звання</label>
                        <input
                            id="edit_rank"
                            type="text"
                            name="rank"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label for="edit_award" class="mb-1 block text-sm font-medium text-slate-300">Нагорода</label>
                        <input
                            id="edit_award"
                            type="text"
                            name="award"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-close-modal="editModal" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800">
                        Скасувати
                    </button>
                    <button type="submit" class="rounded-xl bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-400">
                        Зберегти зміни
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
