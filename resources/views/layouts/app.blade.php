<!DOCTYPE html>
<html lang="uk" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Державні нагороди України')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-8 border-b border-slate-800 pb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Державні нагороди України</h1>
                    <p class="text-sm text-slate-400">Пошук нагороджених осіб, укази та розбір RSS-стрічки</p>
                </div>
                <nav class="flex flex-wrap gap-2 text-sm">
                    <a href="{{ route('search.index') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('search.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Пошук</a>
                    <a href="{{ route('monitoring.index') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('monitoring.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Моніторинг</a>
                    <a href="{{ route('orders.index') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('orders.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Укази</a>
                    <a href="{{ route('parse.index') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('parse.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Розбір</a>
                </nav>
            </div>
        </header>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-700/50 bg-emerald-900/30 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-700/50 bg-rose-900/30 px-4 py-3 text-sm text-rose-100">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="mt-8 border-t border-slate-800 pt-4 text-xs text-slate-500">
            MVP-інтерфейс. Дані зберігаються в локальній базі даних.
        </footer>
    </div>

    <dialog id="confirmationDialog" role="alertdialog" aria-labelledby="confirmationDialog-title" aria-describedby="confirmationDialog-message" class="w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
        <div class="p-6 sm:p-7">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-500/10 text-rose-300">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 id="confirmationDialog-title" class="text-lg font-semibold text-white">Підтвердження дії</h2>
                    <p id="confirmationDialog-message" class="mt-2 text-sm leading-6 text-slate-300">Ви впевнені, що хочете продовжити?</p>
                </div>
            </div>
            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" data-confirm-cancel autofocus class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-400">Скасувати</button>
                <button type="button" data-confirm-submit class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400">Підтвердити</button>
            </div>
        </div>
    </dialog>

    <script src="{{ asset('js/confirmation-dialog.js') }}"></script>
    <script src="{{ asset('js/excel-table.js') }}"></script>
    <script src="{{ asset('js/monitoring.js') }}"></script>
    <script src="{{ asset('js/order-details.js') }}"></script>
    @stack('scripts')
</body>
</html>
