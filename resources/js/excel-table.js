/**
 * Excel-like Table Sorting & Filtering Engine
 * Reusable for any data table with data-excel-table attribute.
 */
export class ExcelTable {
    constructor(tableElement) {
        this.table = typeof tableElement === 'string' ? document.querySelector(tableElement) : tableElement;
        if (!this.table) return;

        this.tableId = this.table.id || 'excel-table-' + Math.random().toString(36).substr(2, 9);
        this.columnFilters = {}; // colIndex => Set of allowed values
        this.currentSort = { colIndex: null, direction: 'asc' };
        this.activeFilterColIndex = null;

        this.initDropdown();
        this.initHeaders();
        this.initToolbar();
        this.updateRowCount();
    }

    initHeaders() {
        const headers = this.table.querySelectorAll('thead th[data-col-index]');
        headers.forEach(th => {
            const colIndex = parseInt(th.dataset.colIndex, 10);
            
            const sortBtn = th.querySelector('[data-action="sort"]') || th.querySelector('.sort-trigger');
            if (sortBtn) {
                sortBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.sortTable(colIndex);
                });
            }

            const filterBtn = th.querySelector('[data-action="filter"]') || th.querySelector('.filter-btn');
            if (filterBtn) {
                filterBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleFilterMenu(filterBtn, colIndex);
                });
            }
        });
    }

    initToolbar() {
        const resetBtn = document.querySelector(`[data-excel-reset="${this.table.id}"]`) || document.getElementById('resetAllFiltersBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.resetAllFilters();
            });
        }
    }

    initDropdown() {
        let dropdown = document.getElementById('excelFilterDropdown');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.id = 'excelFilterDropdown';
            dropdown.className = 'fixed z-50 hidden w-72 max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900/95 p-4 shadow-2xl backdrop-blur-md text-slate-100 text-sm';
            dropdown.innerHTML = `
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <span id="excelFilterMenuTitle" class="text-xs font-semibold uppercase tracking-wider text-slate-300">Фільтр</span>
                    <button type="button" id="excelFilterCloseBtn" class="text-slate-400 hover:text-white text-base leading-none">&times;</button>
                </div>
                <div class="mt-3 space-y-2">
                    <div class="flex gap-2">
                        <button type="button" id="excelSortAscBtn" class="flex-1 rounded-lg border border-slate-800 bg-slate-950/80 px-2 py-1.5 text-xs text-slate-300 hover:border-indigo-500 hover:text-indigo-300">
                            ↑ Сортувати А → Я
                        </button>
                        <button type="button" id="excelSortDescBtn" class="flex-1 rounded-lg border border-slate-800 bg-slate-950/80 px-2 py-1.5 text-xs text-slate-300 hover:border-indigo-500 hover:text-indigo-300">
                            ↓ Сортувати Я → А
                        </button>
                    </div>
                    <div>
                        <input
                            type="text"
                            id="excelFilterSearchInput"
                            placeholder="Пошук у значеннях..."
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 outline-none focus:border-indigo-500"
                        >
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 pt-1">
                        <label class="flex items-center gap-1.5 cursor-pointer select-none">
                            <input type="checkbox" id="excelSelectAllCheckbox" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0">
                            <span>(Виділити все)</span>
                        </label>
                        <button type="button" id="excelClearColFilterBtn" class="text-indigo-400 hover:underline">
                            Скинути
                        </button>
                    </div>
                    <div id="excelFilterValuesList" class="max-h-48 overflow-y-auto space-y-1 rounded-xl border border-slate-800 bg-slate-950/60 p-2 text-xs">
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" id="excelFilterCancelBtn" class="rounded-xl border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800">
                            Скасувати
                        </button>
                        <button type="button" id="excelFilterApplyBtn" class="rounded-xl bg-indigo-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-400">
                            Застосувати
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(dropdown);

            document.getElementById('excelFilterCloseBtn').addEventListener('click', () => this.closeFilterMenu());
            document.getElementById('excelFilterCancelBtn').addEventListener('click', () => this.closeFilterMenu());
            document.getElementById('excelFilterApplyBtn').addEventListener('click', () => this.applyColumnFilter());
            document.getElementById('excelClearColFilterBtn').addEventListener('click', () => this.clearColumnFilter(this.activeFilterColIndex));

            const searchInput = document.getElementById('excelFilterSearchInput');
            searchInput.addEventListener('input', (e) => this.filterValueCheckboxes(e.target.value));

            const selectAll = document.getElementById('excelSelectAllCheckbox');
            selectAll.addEventListener('change', (e) => this.toggleSelectAllValues(e.target.checked));

            document.addEventListener('click', (e) => {
                if (!dropdown.classList.contains('hidden') && !dropdown.contains(e.target) && !e.target.closest('[data-action="filter"], .filter-btn')) {
                    this.closeFilterMenu();
                }
            });
        }
        this.dropdown = dropdown;
    }

    getCellText(row, colIndex) {
        const cell = row.children[colIndex];
        if (!cell) return '';
        if (cell.dataset && cell.dataset.value !== undefined) {
            return cell.dataset.value.trim();
        }
        return cell.innerText.trim();
    }

    getDisplayValue(val) {
        return val === '' ? '(Порожні)' : val;
    }

    getRows() {
        const tbody = this.table.querySelector('tbody');
        if (!tbody) return [];
        return Array.from(tbody.querySelectorAll('tr[data-row], tr.monitoring-row, tr'));
    }

    sortTable(colIndex, explicitDir = null) {
        const tbody = this.table.querySelector('tbody');
        const rows = this.getRows();
        if (!rows.length) return;

        const th = this.table.querySelector(`thead th[data-col-index="${colIndex}"]`);
        const type = th?.dataset?.type || 'text';

        let direction = 'asc';
        if (explicitDir) {
            direction = explicitDir;
        } else if (this.currentSort.colIndex === colIndex) {
            direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        }

        this.currentSort = { colIndex, direction };

        rows.sort((a, b) => {
            const valA = this.getCellText(a, colIndex);
            const valB = this.getCellText(b, colIndex);

            if (type === 'number') {
                const numA = parseFloat(valA) || 0;
                const numB = parseFloat(valB) || 0;
                return direction === 'asc' ? numA - numB : numB - numA;
            }

            if (type === 'date') {
                if (!valA && !valB) return 0;
                if (!valA) return direction === 'asc' ? 1 : -1;
                if (!valB) return direction === 'asc' ? -1 : 1;
                return direction === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
            }

            return direction === 'asc'
                ? valA.localeCompare(valB, 'uk', { sensitivity: 'base' })
                : valB.localeCompare(valA, 'uk', { sensitivity: 'base' });
        });

        rows.forEach(r => tbody.appendChild(r));

        this.table.querySelectorAll('thead th[data-col-index]').forEach(header => {
            const idx = parseInt(header.dataset.colIndex, 10);
            const icon = header.querySelector('.sort-icon') || header.querySelector('[data-sort-icon]');
            if (!icon) return;
            if (idx === colIndex) {
                icon.textContent = direction === 'asc' ? '▲' : '▼';
                icon.classList.add('text-indigo-400');
                icon.classList.remove('text-slate-500');
            } else {
                icon.textContent = '↕';
                icon.classList.remove('text-indigo-400');
                icon.classList.add('text-slate-500');
            }
        });

        if (this.activeFilterColIndex !== null) {
            this.closeFilterMenu();
        }
    }

    toggleFilterMenu(buttonEl, colIndex) {
        if (this.activeFilterColIndex === colIndex && !this.dropdown.classList.contains('hidden')) {
            this.closeFilterMenu();
            return;
        }

        this.activeFilterColIndex = colIndex;
        this.openFilterMenu(buttonEl, colIndex);
    }

    openFilterMenu(buttonEl, colIndex) {
        const th = this.table.querySelector(`thead th[data-col-index="${colIndex}"]`);
        const colTitle = th?.dataset?.title || th?.querySelector('.col-title')?.textContent?.trim() || 'Колонка';
        const type = th?.dataset?.type || 'text';

        const titleEl = document.getElementById('excelFilterMenuTitle');
        const searchInput = document.getElementById('excelFilterSearchInput');
        const valuesList = document.getElementById('excelFilterValuesList');
        const sortAscBtn = document.getElementById('excelSortAscBtn');
        const sortDescBtn = document.getElementById('excelSortDescBtn');

        titleEl.textContent = 'Фільтр: ' + colTitle;
        searchInput.value = '';

        if (type === 'number') {
            sortAscBtn.textContent = '↑ Від меншого до більшого';
            sortDescBtn.textContent = '↓ Від більшого до меншого';
        } else if (type === 'date') {
            sortAscBtn.textContent = '↑ Від старих до нових';
            sortDescBtn.textContent = '↓ Від нових до старих';
        } else {
            sortAscBtn.textContent = '↑ Сортувати А → Я';
            sortDescBtn.textContent = '↓ Сортувати Я → А';
        }

        sortAscBtn.onclick = () => this.sortTable(colIndex, 'asc');
        sortDescBtn.onclick = () => this.sortTable(colIndex, 'desc');

        const rows = this.getRows();
        const uniqueValues = new Set();
        rows.forEach(r => {
            uniqueValues.add(this.getCellText(r, colIndex));
        });

        const sortedValues = Array.from(uniqueValues).sort((a, b) => {
            if (type === 'number') return (parseFloat(a) || 0) - (parseFloat(b) || 0);
            return a.localeCompare(b, 'uk', { sensitivity: 'base' });
        });

        valuesList.innerHTML = '';
        const activeFilter = this.columnFilters[colIndex];

        let allChecked = true;
        sortedValues.forEach(val => {
            const isChecked = activeFilter ? activeFilter.has(val) : true;
            if (!isChecked) allChecked = false;

            const label = document.createElement('label');
            label.className = 'flex items-center gap-2 py-1 px-1.5 rounded hover:bg-slate-900 cursor-pointer select-none filter-item';
            label.dataset.value = val.toLowerCase();

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'val-checkbox rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0';
            checkbox.value = val;
            checkbox.checked = isChecked;

            const span = document.createElement('span');
            span.className = 'text-slate-200 truncate';
            span.textContent = this.getDisplayValue(val);

            label.appendChild(checkbox);
            label.appendChild(span);
            valuesList.appendChild(label);
        });

        document.getElementById('excelSelectAllCheckbox').checked = allChecked;

        const rect = buttonEl.getBoundingClientRect();
        this.dropdown.classList.remove('hidden');

        const dropdownWidth = this.dropdown.offsetWidth || 288;
        const dropdownHeight = this.dropdown.offsetHeight || 360;

        let left = rect.left;
        if (left + dropdownWidth > window.innerWidth - 16) {
            left = window.innerWidth - dropdownWidth - 16;
        }
        if (left < 16) left = 16;

        let top = rect.bottom + 6;
        if (top + dropdownHeight > window.innerHeight - 16) {
            const topAbove = rect.top - dropdownHeight - 6;
            if (topAbove >= 16) {
                top = topAbove;
            } else {
                top = Math.max(16, window.innerHeight - dropdownHeight - 16);
            }
        }

        this.dropdown.style.left = `${left}px`;
        this.dropdown.style.top = `${top}px`;

        searchInput.focus();
    }

    closeFilterMenu() {
        if (this.dropdown) {
            this.dropdown.classList.add('hidden');
        }
        this.activeFilterColIndex = null;
    }

    filterValueCheckboxes(query) {
        const q = query.toLowerCase().trim();
        const items = document.querySelectorAll('#excelFilterValuesList .filter-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(q) ? 'flex' : 'none';
        });
    }

    toggleSelectAllValues(checked) {
        const checkboxes = document.querySelectorAll('#excelFilterValuesList .filter-item');
        checkboxes.forEach(item => {
            if (item.style.display !== 'none') {
                const cb = item.querySelector('.val-checkbox');
                if (cb) cb.checked = checked;
            }
        });
    }

    applyColumnFilter() {
        if (this.activeFilterColIndex === null) return;

        const colIndex = this.activeFilterColIndex;
        const checkboxes = Array.from(document.querySelectorAll('#excelFilterValuesList .val-checkbox'));
        const selectedValues = new Set();
        const totalCount = checkboxes.length;

        checkboxes.forEach(cb => {
            if (cb.checked) {
                selectedValues.add(cb.value);
            }
        });

        if (selectedValues.size === totalCount) {
            delete this.columnFilters[colIndex];
        } else {
            this.columnFilters[colIndex] = selectedValues;
        }

        this.closeFilterMenu();
        this.renderFilters();
    }

    clearColumnFilter(colIndex) {
        if (colIndex === null || colIndex === undefined) return;
        delete this.columnFilters[colIndex];
        this.closeFilterMenu();
        this.renderFilters();
    }

    resetAllFilters() {
        this.columnFilters = {};
        this.renderFilters();
    }

    renderFilters() {
        const rows = this.getRows();
        let visibleCount = 0;
        const activeCols = Object.keys(this.columnFilters).map(Number);

        rows.forEach(row => {
            let matches = true;

            for (const colIndex of activeCols) {
                const allowedSet = this.columnFilters[colIndex];
                if (!allowedSet) continue;

                const cellVal = this.getCellText(row, colIndex);
                if (!allowedSet.has(cellVal)) {
                    matches = false;
                    break;
                }
            }

            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        this.updateRowCount(visibleCount);

        // Update header filter buttons active status
        this.table.querySelectorAll('thead th[data-col-index]').forEach(th => {
            const idx = parseInt(th.dataset.colIndex, 10);
            const btn = th.querySelector('[data-action="filter"], .filter-btn');
            const isFiltered = this.columnFilters[idx] !== undefined;
            if (btn) {
                if (isFiltered) {
                    btn.classList.add('text-indigo-400', 'bg-indigo-950/80');
                    btn.classList.remove('text-slate-400');
                } else {
                    btn.classList.remove('text-indigo-400', 'bg-indigo-950/80');
                    btn.classList.add('text-slate-400');
                }
            }
        });

        // Show/Hide reset button
        const resetBtn = document.querySelector(`[data-excel-reset="${this.table.id}"]`) || document.getElementById('resetAllFiltersBtn');
        if (resetBtn) {
            if (activeCols.length > 0) {
                resetBtn.classList.remove('hidden');
            } else {
                resetBtn.classList.add('hidden');
            }
        }
    }

    updateRowCount(count = null) {
        const countEl = document.querySelector(`[data-excel-count="${this.table.id}"]`) || document.getElementById('filteredRowCount');
        if (countEl) {
            if (count === null) {
                count = this.getRows().length;
            }
            countEl.textContent = count;
        }
    }
}

// Auto-initialize tables
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-excel-table], #monitoringTable').forEach(table => {
            new ExcelTable(table);
        });
    });
}

if (typeof window !== 'undefined') {
    window.ExcelTable = ExcelTable;
}
