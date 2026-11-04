<!DOCTYPE html>
<html lang="uk" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Нагороди Президента України')</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-8 border-b border-slate-800 pb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Нагороди Президента України</h1>
                    <p class="text-sm text-slate-400">Пошук нагороджених осіб, укази та розбір RSS-стрічки</p>
                </div>
                <nav class="flex flex-wrap gap-2 text-sm">
                    <a href="{{ route('search.index') }}" class="rounded-lg px-3 py-2 font-medium {{ request()->routeIs('search.*') ? 'bg-indigo-500 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Пошук</a>
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
</body>
</html>
