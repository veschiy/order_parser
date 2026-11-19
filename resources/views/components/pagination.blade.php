@props(['paginator'])

@if ($paginator)
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
        <p class="text-sm text-slate-400">
            Показано {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} із {{ $paginator->total() }} записів
        </p>

        <div class="flex flex-wrap items-center gap-1" @if ($paginator->lastPage() > 1) show @else hidden @endif>
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">Назад</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">Назад</a>
            @endif

            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $range = 2; // pages shown on each side of the current page

                // Set of page numbers to display: always include first & last, plus a window
                // of ±$range around the current page (clamped to [1, lastPage]). Sorted ascending.
                $toShow = [];
                if ($lastPage <= $range * 2 + 3) {
                    for ($i = 1; $i <= $lastPage; $i++) {
                        $toShow[$i] = true; // small set: show every page
                    }
                } else {
                    $toShow[1] = true;
                    $toShow[$lastPage] = true;

                    for ($i = max(1, $currentPage - $range); $i <= min($lastPage, $currentPage + $range); $i++) {
                        $toShow[$i] = true;
                    }
                }
                ksort($toShow);
                $shown = array_keys($toShow);

                // Insert an ellipsis between two non-adjacent groups only when hidden pages
                // exist on both sides of the gap (never a leading or trailing one).
                $elements = [];
                $prevPage = null;
                foreach ($shown as $value) {
                    if ($prevPage !== null && $value > $prevPage + 1) {
                        $unshown = array_diff(range(1, $lastPage), $shown);
                        $leftHidden = !empty(array_filter($unshown, static fn ($p) => $p < $value));
                        $rightHidden = !empty(array_filter($unshown, static fn ($p) => $p > $prevPage));
                        if ($leftHidden && $rightHidden) {
                            $elements[] = ['type' => 'ellipsis'];
                        }
                    }
                    $elements[] = ['type' => 'page', 'value' => $value];
                    $prevPage = $value;
                }
            @endphp

            @foreach ($elements as $element)
                @if ($element['type'] === 'ellipsis')
                    <span class="rounded-lg px-1 py-1.5 text-sm font-medium text-slate-500">…</span>
                @else
                    @if ($element['value'] === $currentPage)
                        <span class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">{{ $element['value'] }}</span>
                    @elseif ($paginator->onFirstPage() && $element['value'] === 1)
                        <span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">1</span>
                    @elseif (!$paginator->hasMorePages() && $element['value'] === $lastPage)
                        <span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">{{ $element['value'] }}</span>
                    @else
                        <a href="{{ $paginator->url($element['value']) }}" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">{{ $element['value'] }}</a>
                    @endif
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">Далі</a>
            @endif
        </div>
    </div>
@endif
