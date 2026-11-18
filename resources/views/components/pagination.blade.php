@props(['paginator'])

@if ($paginator)
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
        <p class="text-sm text-slate-400">
            Показано {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} із {{ $paginator->total() }} записів
        </p>

        <div class="flex items-center gap-2" @if ($paginator->lastPage() > 1) show @else hidden @endif>
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">Назад</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">Назад</a>
            @endif

            @for ($i = $paginator->firstItem(); $i <= $paginator->lastPage(); $i++)
                @if ($i === $paginator->currentPage())
                    <span class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">{{ $i }}</span>
                @else
                    <a href="{{ $paginator->url($i) }}" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">{{ $i }}</a>
                @endif
            @endfor

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">Далі</a>
            @endif
        </div>
    </div>
@endif
