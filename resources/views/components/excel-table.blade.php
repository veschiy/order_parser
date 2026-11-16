@props([
    'id' => 'excelTable',
    'total' => null,
    'showToolbar' => true,
    'helpText' => 'Натисніть на заголовок або іконку воронки для сортування та фільтрації у стилі Excel',
])

@if ($showToolbar && $total !== null)
    <div class="mb-2 flex items-center justify-between text-xs text-slate-400">
        <div>
            Відображається: <span id="filteredRowCount" data-excel-count="{{ $id }}" class="font-semibold text-slate-200">{{ $total }}</span> із {{ $total }}
        </div>
        @if ($helpText)
            <div class="text-[11px] text-slate-500">
                {{ $helpText }}
            </div>
        @endif
    </div>
@endif

<div class="overflow-x-auto overflow-y-visible rounded-xl border border-slate-800">
    <table id="{{ $id }}" data-excel-table class="min-w-full divide-y divide-slate-800 text-sm">
        @if (isset($header))
            <thead class="bg-slate-900 select-none">
                <tr>
                    {{ $header }}
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-slate-800">
            {{ $slot }}
        </tbody>
    </table>
</div>
