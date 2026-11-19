@extends('layouts.app')

@section('title', 'Моніторинг нагороджень')

@section('content')
    <div class="space-y-6">
        <!-- Top Action & Input Forms -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Manual Entry Form -->
            <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold text-white">Додати особу до моніторингу</h2>
                    <p class="mt-1 text-sm text-slate-400">Введіть дані вручну для перевірки наявності в указах Президента.</p>
                </div>

                <form method="POST" action="{{ route('monitoring.store') }}" class="space-y-4">
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
            </section>

            <!-- CSV Import Form -->
            <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold text-white">Імпорт з CSV</h2>
                    <p class="mt-1 text-sm text-slate-400">Завантажте CSV-файл із переліком осіб для масового моніторингу.</p>
                </div>

                <form method="POST" action="{{ route('monitoring.import') }}" enctype="multipart/form-data" class="space-y-4">
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
                        <a href="{{ route('monitoring.export', $search !== '' ? ['q' => $search] : []) }}" data-monitoring-export data-token="{{ csrf_token() }}" class="rounded-xl border border-slate-700 bg-slate-800/80 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700 hover:text-white">
                            Експорт у CSV
                        </a>
                    </div>
                </form>
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
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Експорт у CSV
                        </a>
                        <form method="POST" action="{{ route('monitoring.clear') }}" onsubmit="return confirm('Видалити всі записи моніторингу?');" class="inline-block">
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
                @endif
            </div>

            <!-- Table Filter & Search Controls Bar -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800/80 bg-slate-950/40 px-4 py-2.5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-xs text-slate-400">
                        Відображається: <span id="filteredRowCount" data-excel-count="monitoringTable" class="font-semibold text-slate-200">{{ $evaluations->count() }}</span> із {{ $totalCount }}
                    </div>
                    <button
                        type="button"
                        id="resetAllFiltersBtn"
                        data-excel-reset="monitoringTable"
                        class="hidden rounded-lg border border-indigo-700/60 bg-indigo-950/60 px-2.5 py-1 text-xs font-medium text-indigo-300 transition hover:bg-indigo-900/80 hover:text-indigo-200"
                    >
                        ✕ Скинути фільтри
                    </button>
                </div>

                <form method="GET" action="{{ route('monitoring.index') }}" class="flex items-center gap-2">
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Швидкий пошук..."
                        class="w-48 sm:w-64 rounded-xl border border-slate-700 bg-slate-900 px-3 py-1.5 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                    <button type="submit" class="rounded-xl bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-slate-700 transition">
                        Пошук
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('monitoring.index') }}" class="rounded-xl border border-slate-700 px-3 py-1.5 text-sm text-slate-400 hover:text-white transition">
                            Скинути
                        </a>
                    @endif
                </form>
            </div>

            @if ($evaluations->isEmpty())
                <p class="text-sm text-slate-400">
                    @if ($search !== '')
                        За запитом «{{ $search }}» нічого не знайдено.
                    @else
                        Записів у моніторингу поки немає. Додайте особу вручну або імпортуйте CSV файл.
                    @endif
                </p>
            @else
                <x-excel-table id="monitoringTable" :total="$paginator->total()" :show-toolbar="false">
                    <x-slot:header>
                        <x-excel-table.th col="0" type="text">Аркуш</x-excel-table.th>
                        <x-excel-table.th col="1" type="text">ПІБ</x-excel-table.th>
                        <x-excel-table.th col="2" type="date">Дата подання</x-excel-table.th>
                        <x-excel-table.th col="3" type="text">Нагорода</x-excel-table.th>
                        <x-excel-table.th col="4" type="number" align="center">Бал</x-excel-table.th>
                        <x-excel-table.th col="5" type="text">Збіги в указах</x-excel-table.th>
                        <th class="px-3 py-3 text-right font-medium text-slate-300">Дії</th>
                    </x-slot:header>

                    @foreach ($evaluations as $evaluation)
                        @php
                            $rec = $evaluation->record;
                            $score = $evaluation->score;
                            $matchesSummary = empty($evaluation->matches)
                                ? 'Не знайдено'
                                : implode(', ', array_map(static fn ($m) => ($m->awardee->order ? $m->awardee->order->displayNumber() : 'Указ #'.$m->awardee->order_id), $evaluation->matches));
                        @endphp
                        <tr class="hover:bg-slate-800/40 monitoring-row" id="row-{{ $rec->id }}" data-row="{{ $rec->id }}">
                            <!-- Sheet (col 0) -->
                            <td class="px-3 py-3 text-slate-300" data-col="sheet" data-value="{{ $rec->sheet ?? '' }}">
                                {{ $rec->sheet ?? '—' }}
                            </td>

                            <!-- Full Name (col 1) -->
                            <td class="px-3 py-3 font-medium text-slate-100" data-col="full_name" data-value="{{ $rec->full_name }}">
                                <div class="flex items-center gap-2">
                                    <span>{{ $rec->full_name }}</span>
                                </div>
                            </td>

                            <!-- Submission Date (col 2) -->
                            <td class="px-3 py-3 text-slate-300" data-col="submission_date" data-value="{{ $rec->submission_date ? $rec->submission_date->format('Y-m-d') : '' }}">
                                {{ $rec->submission_date ? $rec->submission_date->format('d.m.Y') : '—' }}
                            </td>

                            <!-- Award (col 3) -->
                            <td class="px-3 py-3 text-slate-300" data-col="award" data-value="{{ $rec->award ?? '' }}">
                                {{ $rec->award ?? '—' }}
                            </td>

                            <!-- Score Column (col 4) -->
                            <td class="px-3 py-3 text-center" data-col="score" data-value="{{ $score }}">
                                @if ($score === 0)
                                    <span class="inline-flex items-center rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-400" title="ПІБ не знайдено в указах (0 балів)">
                                        0
                                    </span>
                                @elseif ($score === 1)
                                    <span class="inline-flex items-center rounded-full bg-amber-950/80 border border-amber-700/60 px-2.5 py-1 text-xs font-semibold text-amber-300" title="Збіг за ПІБ (+1 бал)">
                                        1
                                    </span>
                                @elseif ($score === 2)
                                    <span class="inline-flex items-center rounded-full bg-blue-950/80 border border-blue-700/60 px-2.5 py-1 text-xs font-semibold text-blue-300" title="Збіг за ПІБ + одна з характеристик: звання, Нагорода або Дата протягом 6 місяців після подання (+2 бали)">
                                        2
                                    </span>
                                @elseif ($score === 3)
                                    <span class="inline-flex items-center rounded-full bg-purple-950/80 border border-purple-700/60 px-2.5 py-1 text-xs font-semibold text-purple-300" title="ПІБ + дві з характеристик: звання, Нагорода, Дата протягом 6 місяців після подання (+3 бали)">
                                        3
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-950/80 border border-emerald-600/60 px-2.5 py-1 text-xs font-bold text-emerald-300" title="ПІБ + звання + Нагорода + Дата протягом 6 місяців після подання (максимум, +4 бали)">
                                        {{ $score }}
                                    </span>
                                @endif
                            </td>

                            <!-- Matches in decrees (col 5) -->
                            <td class="px-3 py-3 text-xs" data-col="matches" data-value="{{ $matchesSummary }}">
                                @if (empty($evaluation->matches))
                                    <span class="text-slate-500">Не знайдено</span>
                                @else
                                    <div class="space-y-1">
                                        @foreach ($evaluation->matches as $match)
                                            @php
                                                $order = $match->awardee->order;
                                            @endphp
                                            <div class="rounded-lg bg-slate-950/60 p-2 border border-slate-800">
                                                <div class="flex items-center justify-between gap-2">
                                                    @if ($order)
                                                        <a href="{{ route('orders.show', $order->id) }}" class="font-medium text-indigo-300 hover:underline">
                                                            {{ $order->displayNumber() }}
                                                        </a>
                                                    @else
                                                        <span class="text-slate-300">Указ #{{ $match->awardee->order_id }}</span>
                                                    @endif
                                                    <span class="text-[11px] font-semibold text-slate-300 bg-slate-800 px-1.5 py-0.5 rounded">
                                                        бал: {{ $match->score }}
                                                    </span>
                                                </div>
                                                <div class="mt-0.5 text-slate-400">
                                                    {{ $match->awardee->award ?? '—' }}
                                                </div>
                                                <div class="mt-1 flex flex-wrap gap-1 text-[10px]">
                                                    <span class="rounded px-1.5 py-0.5 bg-indigo-900/40 text-indigo-300">ПІБ ✓</span>
                                                    @if ($match->rankMatched)
                                                        <span class="rounded px-1.5 py-0.5 bg-purple-900/40 text-purple-300">Звання ✓</span>
                                                    @endif
                                                    @if ($match->awardMatched)
                                                        <span class="rounded px-1.5 py-0.5 bg-emerald-900/40 text-emerald-300">Нагорода ✓</span>
                                                    @endif
                                                    @if ($match->dateMatched)
                                                        <span class="rounded px-1.5 py-0.5 bg-blue-900/40 text-blue-300">Дата протягом 6 місяців після подання ✓</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        data-edit-monitoring
                                        data-id="{{ $rec->id }}"
                                        data-sheet="{{ $rec->sheet ?? '' }}"
                                        data-full-name="{{ $rec->full_name }}"
                                        data-submission-date="{{ $rec->submission_date ? $rec->submission_date->format('Y-m-d') : '' }}"
                                        data-rank="{{ $rec->rank ?? '' }}"
                                        data-award="{{ $rec->award ?? '' }}"
                                        class="rounded-lg border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-indigo-400 hover:text-indigo-300 transition"
                                    >
                                        Редагувати
                                    </button>

                                    <form method="POST" action="{{ route('monitoring.destroy', $rec->id) }}" onsubmit="return confirm('Видалити цей запис?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-rose-900/60 px-2 py-1 text-xs text-rose-400 hover:bg-rose-950/50">
                                            Видалити
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-excel-table>

                <x-pagination :paginator="$paginator" />
            @endif
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
