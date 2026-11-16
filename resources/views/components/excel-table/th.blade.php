@props([
    'col' => 0,
    'type' => 'text',
    'sortable' => true,
    'filterable' => true,
    'align' => 'left',
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center justify-center',
        'right' => 'text-right justify-end',
        default => 'text-left justify-between',
    };
    $textAlignment = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };
@endphp

<th
    {{ $attributes->merge([
        'class' => "relative px-3 py-3 {$textAlignment} font-medium text-slate-300",
        'data-col-index' => $col,
        'data-type' => $type,
    ]) }}
>
    <div class="flex items-center {{ $alignClass }} gap-1">
        @if ($sortable)
            <button type="button" data-action="sort" class="sort-trigger flex items-center gap-1 hover:text-white group">
                <span class="col-title">{{ $slot }}</span>
                <span class="sort-icon text-slate-500 group-hover:text-slate-300 text-xs">↕</span>
            </button>
        @else
            <span class="col-title">{{ $slot }}</span>
        @endif

        @if ($filterable)
            <button type="button" data-action="filter" class="filter-btn rounded p-1 hover:bg-slate-800 text-slate-400 hover:text-indigo-300 transition" title="Фільтр">
                <svg class="w-3.5 h-3.5 filter-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            </button>
        @endif
    </div>
</th>
