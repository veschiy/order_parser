@extends('layouts.app')

@section('title', 'Розбір стрічки або наказу')

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
            <h2 class="text-xl font-semibold text-white">Розбір RSS-стрічки</h2>
            <p class="mt-1 text-sm text-slate-400">Вкажіть URL RSS. Будуть взяті тільки документи з описом «Про відзначення державними нагородами України».</p>

            <form method="POST" action="{{ route('parse.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="mode" value="rss">
                <div>
                    <label for="rss_url" class="mb-1 block text-sm font-medium text-slate-300">URL RSS</label>
                    <input
                        id="rss_url"
                        type="url"
                        name="rss_url"
                        value="{{ old('rss_url', 'https://www.president.gov.ua/rss/documents/all.rss') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                    >
                </div>
                <div>
                    <label for="max_items" class="mb-1 block text-sm font-medium text-slate-300">Максимум документів (необов’язково)</label>
                    <input
                        id="max_items"
                        type="number"
                        min="1"
                        max="100"
                        name="max_items"
                        value="{{ old('max_items') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                    >
                </div>
                <button type="submit" class="rounded-xl bg-indigo-500 px-5 py-3 font-medium text-white transition hover:bg-indigo-400">
                    Розібрати RSS
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
            <h2 class="text-xl font-semibold text-white">Розбір одного наказу</h2>
            <p class="mt-1 text-sm text-slate-400">Вкажіть пряме посилання на документ. Це корисно для старих або довільних наказів.</p>

            <form method="POST" action="{{ route('parse.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="mode" value="single">
                <div>
                    <label for="document_url" class="mb-1 block text-sm font-medium text-slate-300">URL документа</label>
                    <input
                        id="document_url"
                        type="url"
                        name="document_url"
                        value="{{ old('document_url') }}"
                        placeholder="https://www.president.gov.ua/documents/..."
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 placeholder-slate-500 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                    >
                </div>
                <button type="submit" class="rounded-xl bg-indigo-500 px-5 py-3 font-medium text-white transition hover:bg-indigo-400">
                    Розібрати документ
                </button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <h3 class="text-lg font-semibold text-white">Підказки</h3>
        <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-slate-400">
            <li>Мережеві запити будуть виконуватися з браузероподібним User-Agent.</li>
            <li>Накази зберігатимуться в локальній базі даних SQLite.</li>
            <li>Пошук перевірятиме, чи особа вже була нагороджена.</li>
        </ul>
    </section>
@endsection
