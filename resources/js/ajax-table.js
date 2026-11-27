import { escapeHtml } from './html.js';

/**
 * AJAX-powered table loading, pagination, sorting and filtering.
 * Each table declares its configuration via data attributes.
 */

export class AjaxTable {
    constructor(tableId, config) {
        this.tableId = tableId;
        this.url = config.url;
        this.tbodySelector = config.tbodySelector || `#${tableId} tbody`;
        this.paginationSelector = config.paginationSelector || `#${tableId}-pagination`;
        this.emptyMessageSelector = config.emptyMessageSelector || `#${tableId}-empty`;
        this.loadingSelector = config.loadingSelector || `#${tableId}-loading`;
        this.countSelector = config.countSelector || null;
        this.defaultParams = config.defaultParams || {};
        this.renderRow = config.renderRow;
        this.renderComplete = config.renderComplete || null;
        this.onLoad = config.onLoad || null;

        this.currentPage = 1;
        this.currentSort = config.defaultSort || null;
        this.currentDirection = config.defaultDirection || 'asc';
        this.isLoading = false;
        this.allData = [];

        // Column filters: colIndex => Set of selected values
        this.columnFilters = {};
        this.filterColumnMap = config.filterColumnMap || {};
        this.activeFilterColIndex = null;

        // Track all distinct values per column (for "all checked by default" logic)
        this.columnFilterAllValues = {};

        // Cached distinct values from the main JSON response (e.g. distinctMatches)
        this.cachedDistinctValues = {};

        // Merge config methods onto the instance so subclasses can override them via config.
        if (typeof config.getSortColumnMap === 'function') {
            this.getSortColumnMap = config.getSortColumnMap.bind(this);
        }

        this.bindPagination();
        this.bindSortHeaders();
        this.bindFilterHeaders();
        this.initFilterDropdown();
    }

    initialize() {
        return this.load();
    }

    buildParams(overrides = {}) {
        const params = {
            ...this.defaultParams,
            page: this.currentPage,
            ...overrides,
        };

        if (this.currentSort) {
            params.sort = this.currentSort;
            params.direction = this.currentDirection;
        }

        // Build filter params: filters[column_name]=val1,val2
        const activeFilters = this.getActiveFilters();
        if (activeFilters.length > 0) {
            params.filters = activeFilters;
        }

        return params;
    }

    getActiveFilters() {
        const filters = [];
        for (const [colIndex, selectedValues] of Object.entries(this.columnFilters)) {
            const columnName = this.filterColumnMap[colIndex];
            if (columnName) {
                // Send filter even when empty (all unchecked) — server will return no results
                filters.push(columnName + ':' + Array.from(selectedValues).join(','));
            }
        }
        return filters;
    }

    buildUrl(params) {
        const url = new URL(this.url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                if (Array.isArray(value)) {
                    value.forEach(item => {
                        url.searchParams.append(key + '[]', String(item));
                    });
                } else {
                    url.searchParams.set(key, String(value));
                }
            }
        });
        return url.toString();
    }

    showLoading() {
        if (this.loadingSelector) {
            const el = document.querySelector(this.loadingSelector);
            el?.classList.remove('hidden');
        }
    }

    hideLoading() {
        if (this.loadingSelector) {
            const el = document.querySelector(this.loadingSelector);
            el?.classList.add('hidden');
        }
    }

    async load(overrides = {}) {
        if (this.isLoading) return;
        this.isLoading = true;
        this.showLoading();

        try {
            const params = this.buildParams(overrides);
            const url = this.buildUrl(params);

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const json = await response.json();
            this.allData = Array.isArray(json.data) ? json.data : [];

            // Cache distinct values from the main JSON response
            if (Array.isArray(json.distinctMatches)) {
                this.cachedDistinctValues.matches = json.distinctMatches;
            }

            this.render(this.allData, json.pagination || {});

            if (this.onLoad) {
                this.onLoad(json);
            }
        } catch (error) {
            console.error('AjaxTable load error:', error);
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    render(data, pagination) {
        const tbody = document.querySelector(this.tbodySelector);
        if (!tbody) return;

        const emptyEl = this.emptyMessageSelector ? document.querySelector(this.emptyMessageSelector) : null;

        if (data.length === 0) {
            tbody.innerHTML = '';
            emptyEl?.classList.remove('hidden');
        } else {
            emptyEl?.classList.add('hidden');

            const html = data.map(row => this.renderRow(row)).join('');
            tbody.innerHTML = html;
        }

        this.renderPagination(pagination);
        this.updateCount(pagination);

        if (this.renderComplete) {
            this.renderComplete(data, pagination);
        }
    }

    renderPagination(pagination) {
        const container = this.paginationSelector ? document.querySelector(this.paginationSelector) : null;
        if (!container) return;

        if (pagination.total === undefined || pagination.total === 0) {
            container.innerHTML = '';
            return;
        }

        const { current_page, last_page, total, from, to } = pagination;

        if (last_page <= 1) {
            container.innerHTML = `
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
                    <p class="text-sm text-slate-400">Показано ${from}–${to} із ${total} записів</p>
                </div>`;
            return;
        }

        const pageNumbers = this.renderPaginationPages(current_page, last_page);
        const backBtn = this.renderPaginationButton('Назад', current_page - 1, current_page !== 1);
        const nextBtn = this.renderPaginationButton('Далі', current_page + 1, current_page !== last_page);

        container.innerHTML = `
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
                <p class="text-sm text-slate-400">Показано ${from}–${to} із ${total} записів</p>
                <div class="flex flex-wrap items-center gap-1">
                    ${backBtn}
                    ${pageNumbers}
                    ${nextBtn}
                </div>
            </div>`;

        this.bindPaginationLinks(container);
    }

    renderPaginationPages(currentPage, lastPage) {
        const pages = new Set([1, lastPage]);
        if (lastPage <= 7) {
            for (let page = 1; page <= lastPage; page++) pages.add(page);
        } else {
            for (let page = Math.max(1, currentPage - 2); page <= Math.min(lastPage, currentPage + 2); page++) {
                pages.add(page);
            }
        }

        const shownPages = [...pages].sort((a, b) => a - b);
        const elements = [];
        let previousPage = null;

        for (const page of shownPages) {
            if (previousPage !== null && page > previousPage + 1) {
                elements.push('<span class="rounded-lg px-1 py-1.5 text-sm font-medium text-slate-500">…</span>');
            }
            elements.push(this.renderPageNumber(page, currentPage));
            previousPage = page;
        }

        return elements.join('');
    }

    renderPageNumber(page, currentPage) {
        if (page === currentPage) {
            return `<span class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">${page}</span>`;
        }

        return `<a href="#" class="ajax-page-link rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700" data-page="${page}">${page}</a>`;
    }

    renderPaginationButton(label, page, enabled) {
        if (!enabled) {
            return `<span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">${label}</span>`;
        }

        return `<a href="#" class="ajax-page-link rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700" data-page="${page}">${label}</a>`;
    }

    bindPaginationLinks(container) {
        container.querySelectorAll('.ajax-page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = Number.parseInt(link.dataset.page, 10);
                void this.load();
            });
        });
    }

    updateCount(pagination) {
        if (this.countSelector) {
            const el = document.querySelector(this.countSelector);
            if (el && pagination.total !== undefined) {
                el.textContent = pagination.total;
            }
        }
    }


    bindPagination() {
        // Pagination links are bound dynamically in renderPagination() after each load.
        // This method is a placeholder for any initial setup if needed.
    }

    bindSortHeaders() {
        const headers = document.querySelectorAll(`#${this.tableId} thead th[data-col-index]`);
        headers.forEach(th => {
            const sortBtn = th.querySelector('[data-action="sort"]');
            if (!sortBtn) return;

            sortBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const colIndex = Number.parseInt(th.dataset.colIndex, 10);
                const col = th.dataset.title || th.querySelector('.col-title')?.textContent?.trim() || '';

                const sortMap = this.getSortColumnMap();
                const sortCol = sortMap[colIndex] || col;

                if (this.currentSort === sortCol) {
                    this.currentDirection = this.currentDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.currentSort = sortCol;
                    this.currentDirection = 'asc';
                }

                this.currentPage = 1;
                void this.load();

                this.updateSortIcons(colIndex);
            });
        });
    }

    getSortColumnMap() {
        return {};
    }

    updateSortIcons(activeColIndex) {
        const headers = document.querySelectorAll(`#${this.tableId} thead th[data-col-index]`);
        headers.forEach(th => {
            const idx = Number.parseInt(th.dataset.colIndex, 10);
            const icon = th.querySelector('.sort-icon');
            if (!icon) return;
            if (idx === activeColIndex) {
                icon.textContent = this.currentDirection === 'asc' ? '▲' : '▼';
                icon.classList.add('text-indigo-400');
                icon.classList.remove('text-slate-500');
            } else {
                icon.textContent = '↕';
                icon.classList.remove('text-indigo-400');
                icon.classList.add('text-slate-500');
            }
        });
    }

    // ---- Column Filter Methods ----

    bindFilterHeaders() {
        const headers = document.querySelectorAll(`#${this.tableId} thead th[data-col-index]`);
        headers.forEach(th => {
            const filterBtn = th.querySelector('[data-action="filter"]');
            if (!filterBtn) return;

            filterBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const colIndex = Number.parseInt(th.dataset.colIndex, 10);
                this.toggleFilterDropdown(filterBtn, colIndex);
            });
        });
    }

    initFilterDropdown() {
        let dropdown = document.getElementById(`${this.tableId}FilterDropdown`);
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.id = `${this.tableId}FilterDropdown`;
            dropdown.className = 'fixed z-50 hidden w-72 max-h-[calc(100vh-2rem)] rounded-2xl border border-slate-700 bg-slate-900/95 shadow-2xl backdrop-blur-md text-slate-100 text-sm';
            document.body.appendChild(dropdown);
        }

        dropdown.innerHTML = `
            <div class="flex items-center justify-between p-3 border-b border-slate-800">
                <span id="${this.tableId}FilterTitle" class="text-xs font-semibold uppercase tracking-wider text-slate-300">Фільтр</span>
                <button type="button" id="${this.tableId}FilterClose" class="text-slate-400 hover:text-white text-lg leading-none">&times;</button>
            </div>
            <div class="p-3 space-y-2">
                <div class="flex gap-2">
                    <button type="button" id="${this.tableId}FilterSortAsc" class="flex-1 rounded-lg border border-slate-800 bg-slate-950/80 px-2 py-1.5 text-xs text-slate-300 hover:border-indigo-500 hover:text-indigo-300 transition">
                        ↑ Сортувати А → Я
                    </button>
                    <button type="button" id="${this.tableId}FilterSortDesc" class="flex-1 rounded-lg border border-slate-800 bg-slate-950/80 px-2 py-1.5 text-xs text-slate-300 hover:border-indigo-500 hover:text-indigo-300 transition">
                        ↓ Сортувати Я → А
                    </button>
                </div>
                <div class="relative">
                    <input type="search" id="${this.tableId}FilterSearch" placeholder="Пошук у значеннях..." class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 outline-none focus:border-indigo-500">
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 pt-1">
                    <label class="flex items-center gap-1.5 cursor-pointer select-none">
                        <input type="checkbox" id="${this.tableId}FilterSelectAllCheckbox" class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-0">
                        <span>(Відмітити все)</span>
                    </label>
                    <button type="button" id="${this.tableId}FilterClear" class="text-indigo-400 hover:underline text-xs">
                        Скинути
                    </button>
                </div>
                <div id="${this.tableId}FilterCheckboxList" class="max-h-60 overflow-y-auto space-y-0.5 pr-1 rounded-xl border border-slate-800 bg-slate-950/60 p-2"></div>
            </div>
            <div class="flex items-center justify-end gap-2 p-3 border-t border-slate-800">
                <button type="button" id="${this.tableId}FilterCancel" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-400 hover:text-white transition">
                    Скасувати
                </button>
                <button type="button" id="${this.tableId}FilterApply" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 transition">
                    Застосувати
                </button>
            </div>
        `;

        // Bind dropdown events
        document.getElementById(`${this.tableId}FilterClose`).addEventListener('click', () => this.closeFilterDropdown());
        document.getElementById(`${this.tableId}FilterCancel`).addEventListener('click', () => this.closeFilterDropdown());
        document.getElementById(`${this.tableId}FilterApply`).addEventListener('click', () => this.applyFilterDropdown());
        document.getElementById(`${this.tableId}FilterClear`).addEventListener('click', () => this.clearFilterDropdown());

        const selectAllCheckbox = document.getElementById(`${this.tableId}FilterSelectAllCheckbox`);
        selectAllCheckbox.addEventListener('change', (e) => this.toggleSelectAllFilterCheckboxes(e.target.checked));

        // Sort buttons — apply sorting to table rows (server-side)
        document.getElementById(`${this.tableId}FilterSortAsc`).addEventListener('click', () => this.sortTable('asc'));
        document.getElementById(`${this.tableId}FilterSortDesc`).addEventListener('click', () => this.sortTable('desc'));

        const searchInput = document.getElementById(`${this.tableId}FilterSearch`);
        if (searchInput) {
            searchInput.addEventListener('input', () => this.filterCheckboxList(searchInput.value));
        }

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !e.target.closest(`[data-action="filter"]`)) {
                this.closeFilterDropdown();
            }
        });
    }

    toggleFilterDropdown(button, colIndex) {
        const dropdown = document.getElementById(`${this.tableId}FilterDropdown`);
        if (!dropdown) return;

        // If clicking the same column, close the dropdown
        if (this.activeFilterColIndex === colIndex && !dropdown.classList.contains('hidden')) {
            this.closeFilterDropdown();
            return;
        }

        this.activeFilterColIndex = colIndex;
        const columnName = this.filterColumnMap[colIndex];

        // Update title with "ФІЛЬТР:" prefix
        const titleEl = document.getElementById(`${this.tableId}FilterTitle`);
        if (titleEl) {
            const th = document.querySelector(`#${this.tableId} th[data-col-index="${colIndex}"] .col-title`);
            const colTitle = th ? th.textContent.trim() : 'Фільтр';
            titleEl.textContent = 'ФІЛЬТР: ' + colTitle;
        }

        // Update sort button labels based on column type
        const sortAscBtn = document.getElementById(`${this.tableId}FilterSortAsc`);
        const sortDescBtn = document.getElementById(`${this.tableId}FilterSortDesc`);
        if (sortAscBtn && sortDescBtn) {
            const th = document.querySelector(`#${this.tableId} th[data-col-index="${colIndex}"]`);
            const type = th?.dataset?.type || 'text';
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
        }

        // Fetch distinct values from server
        void this.loadDistinctValues(colIndex, columnName, button);
    }

    async loadDistinctValues(colIndex, columnName, button) {
        const dropdown = document.getElementById(`${this.tableId}FilterDropdown`);
        const listEl = document.getElementById(`${this.tableId}FilterCheckboxList`);
        const searchInput = document.getElementById(`${this.tableId}FilterSearch`);
        if (!dropdown || !listEl) return;

        // Position dropdown near button
        const rect = button.getBoundingClientRect();
        dropdown.style.top = Math.min(rect.bottom + 4, window.innerHeight - 400) + 'px';
        dropdown.style.left = Math.min(rect.left, window.innerWidth - 300) + 'px';
        dropdown.classList.remove('hidden');

        if (searchInput) searchInput.value = '';

        // Use cached distinct values from the main JSON response (no separate AJAX request)
        if (columnName in this.cachedDistinctValues) {
            const values = this.cachedDistinctValues[columnName];
            this.renderDistinctCheckboxes(values, listEl);
            return;
        }

        // Show loading state for columns without cached values
        listEl.innerHTML = '<div class="py-4 text-center text-xs text-slate-500">Завантаження...</div>';

        // Fetch distinct values from server for other columns
        try {
            const params = { ...this.defaultParams, column: columnName };
            const url = new URL(this.url + '/distinct', window.location.origin);
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    url.searchParams.set(key, String(value));
                }
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load distinct values');
            }

            const json = await response.json();
            const values = json.values || [];

            // If no server distinct endpoint, fall back to client-side distinct from current data
            if (values.length === 0) {
                this.renderClientSideDistinct(colIndex, listEl);
            } else {
                this.renderDistinctCheckboxes(values, listEl);
            }
        } catch (error) {
            console.error('AjaxTable distinct values error:', error);
            // Fall back to client-side distinct values
            this.renderClientSideDistinct(colIndex, listEl);
        }
    }

    renderClientSideDistinct(colIndex, listEl) {
        // Get distinct values from current page data using the cell data-col attribute
        const rows = document.querySelectorAll(`${this.tbodySelector} tr`);
        const valueSet = new Set();

        rows.forEach(tr => {
            const cell = tr.querySelector(`td[data-col-index="${colIndex}"]`);
            if (cell) {
                const value = cell.dataset.value || cell.textContent.trim();
                if (value && value !== '—') {
                    valueSet.add(value);
                }
            }
        });

        const values = Array.from(valueSet).sort((a, b) => a.localeCompare(b, 'uk'));
        this.renderDistinctCheckboxes(values, listEl);
    }

    renderDistinctCheckboxes(values, listEl) {
        const colIndex = this.activeFilterColIndex;
        const allValues = new Set(values);

        // Store all distinct values for this column
        this.columnFilterAllValues[colIndex] = allValues;

        // Get currently selected values, or default to all values (all checked by default)
        let selected;
        if (this.columnFilters[colIndex] !== undefined) {
            selected = this.columnFilters[colIndex];
        } else {
            selected = new Set(values);
        }

        if (values.length === 0) {
            listEl.innerHTML = '<div class="py-4 text-center text-xs text-slate-500">Немає значень для відображення</div>';
            // Update select-all checkbox
            const selectAllCheckbox = document.getElementById(`${this.tableId}FilterSelectAllCheckbox`);
            if (selectAllCheckbox) selectAllCheckbox.checked = true;
            return;
        }

        let html = '';
        let checkedCount = 0;
        for (const value of values) {
            const isChecked = selected.has(value);
            if (isChecked) checkedCount++;
            const escapedValue = escapeHtml(value);
            const displayLabel = escapeHtml(value === '__empty__' ? 'Пусто' : value);
            html += `
                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-slate-800/50 cursor-pointer filter-checkbox-item">
                    <input type="checkbox" value="${escapedValue}" ${isChecked ? 'checked' : ''} class="rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0 filter-checkbox">
                    <span class="text-xs text-slate-300 truncate max-w-full">${displayLabel}</span>
                </label>`;
        }

        listEl.innerHTML = html;

        // Update select-all checkbox state
        const selectAllCheckbox = document.getElementById(`${this.tableId}FilterSelectAllCheckbox`);
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === values.length;
        }
    }

    filterCheckboxList(query) {
        const listEl = document.getElementById(`${this.tableId}FilterCheckboxList`);
        if (!listEl) return;

        const items = listEl.querySelectorAll('.filter-checkbox-item');
        const lowerQuery = query.toLowerCase();

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(lowerQuery) ? '' : 'none';
        });
    }

    toggleSelectAllFilterCheckboxes(checked) {
        const checkboxes = document.querySelectorAll(`#${this.tableId}FilterDropdown .filter-checkbox`);
        checkboxes.forEach(cb => cb.checked = checked);
    }

    sortTable(direction) {
        const colIndex = this.activeFilterColIndex;
        if (colIndex === null) return;

        const th = document.querySelector(`#${this.tableId} th[data-col-index="${colIndex}"]`);
        const col = th?.dataset?.title || th?.querySelector('.col-title')?.textContent?.trim() || '';

        const sortMap = this.getSortColumnMap();
        const sortCol = sortMap[colIndex] || col;

        this.currentSort = sortCol;
        this.currentDirection = direction;
        this.currentPage = 1;

        this.updateSortIcons(colIndex);
        this.closeFilterDropdown();
        void this.load();
    }

    applyFilterDropdown() {
        const colIndex = this.activeFilterColIndex;
        if (colIndex === null) return;

        const checkboxes = document.querySelectorAll(`#${this.tableId}FilterDropdown .filter-checkbox:checked`);
        const selectedValues = new Set();
        checkboxes.forEach(cb => selectedValues.add(cb.value));

        const allValues = this.columnFilterAllValues[colIndex];

        // If all values selected (same as all available) — no filter, show everything
        if (selectedValues.size === allValues?.size && this.isSubsetOf(selectedValues, allValues)) {
            delete this.columnFilters[colIndex];
        }
        // If nothing selected — store empty set (will produce empty table)
        else if (selectedValues.size === 0) {
            this.columnFilters[colIndex] = new Set();
        }
        // Some values selected — store as filter
        else {
            this.columnFilters[colIndex] = selectedValues;
        }

        // Update filter button icon to show active filter
        this.updateFilterButtons();

        this.closeFilterDropdown();
        this.currentPage = 1;
        void this.load();
    }

    isSubsetOf(subset, superset) {
        for (const item of subset) {
            if (!superset.has(item)) return false;
        }
        return true;
    }

    clearFilterDropdown() {
        const colIndex = this.activeFilterColIndex;
        if (colIndex !== null) {
            // Clearing a filter means going back to "all checked" (show everything)
            delete this.columnFilters[colIndex];
            this.updateFilterButtons();
        }
        this.closeFilterDropdown();
        this.currentPage = 1;
        void this.load();
    }

    closeFilterDropdown() {
        const dropdown = document.getElementById(`${this.tableId}FilterDropdown`);
        if (dropdown) {
            dropdown.classList.add('hidden');
        }
        this.activeFilterColIndex = null;
    }

    updateFilterButtons() {
        const headers = document.querySelectorAll(`#${this.tableId} thead th[data-col-index]`);
        headers.forEach(th => {
            const colIndex = Number.parseInt(th.dataset.colIndex, 10);
            const filterBtn = th.querySelector('[data-action="filter"]');
            if (!filterBtn) return;

            const filterSet = this.columnFilters[colIndex];
            const allValues = this.columnFilterAllValues[colIndex];

            // A filter is "active" (icon colored) when some values are excluded
            // (i.e., not all values are selected). If no filter entry exists or
            // all values are selected, the icon should be dull (no filtering).
            let isActive = false;
            if (filterSet !== undefined) {
                isActive = filterSet.size === 0 || filterSet.size < (allValues?.size ?? 0);
            }
            // else: no filter entry — not active (dull icon, all shown)

            if (isActive) {
                filterBtn.classList.add('text-indigo-400');
                filterBtn.classList.remove('text-slate-400');
            } else {
                filterBtn.classList.remove('text-indigo-400');
                filterBtn.classList.add('text-slate-400');
            }
        });
    }

    resetAllFilters() {
        this.columnFilters = {};
        this.updateFilterButtons();
        this.currentPage = 1;
        void this.load();
    }
}
