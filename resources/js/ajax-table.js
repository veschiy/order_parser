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

        // Merge config methods onto the instance so subclasses can override them via config.
        if (typeof config.getSortColumnMap === 'function') {
            this.getSortColumnMap = config.getSortColumnMap.bind(this);
        }

        this.bindPagination();
        this.bindSortHeaders();
        this.bindFilterHeaders();
        this.initFilterDropdown();
        this.load();
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
            if (selectedValues && selectedValues.size > 0) {
                const columnName = this.filterColumnMap[colIndex];
                if (columnName) {
                    filters.push(columnName + ':' + Array.from(selectedValues).join(','));
                }
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
            if (el) el.classList.remove('hidden');
        }
    }

    hideLoading() {
        if (this.loadingSelector) {
            const el = document.querySelector(this.loadingSelector);
            if (el) el.classList.add('hidden');
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
            if (emptyEl) emptyEl.classList.remove('hidden');
        } else {
            if (emptyEl) emptyEl.classList.add('hidden');

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

        let pagesHtml = '';
        const range = 2;
        const toShow = {};

        if (last_page <= range * 2 + 3) {
            for (let i = 1; i <= last_page; i++) toShow[i] = true;
        } else {
            toShow[1] = true;
            toShow[last_page] = true;
            for (let i = Math.max(1, current_page - range); i <= Math.min(last_page, current_page + range); i++) {
                toShow[i] = true;
            }
        }

        const shown = Object.keys(toShow).map(Number).sort((a, b) => a - b);
        const elements = [];
        let prevPage = null;

        for (const value of shown) {
            if (prevPage !== null && value > prevPage + 1) {
                elements.push('<span class="rounded-lg px-1 py-1.5 text-sm font-medium text-slate-500">…</span>');
            }
            if (value === current_page) {
                elements.push(`<span class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white">${value}</span>`);
            } else {
                elements.push(`<a href="#" class="ajax-page-link rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700" data-page="${value}">${value}</a>`);
            }
            prevPage = value;
        }

        const backBtn = current_page === 1
            ? '<span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">Назад</span>'
            : `<a href="#" class="ajax-page-link rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700" data-page="${current_page - 1}">Назад</a>`;

        const nextBtn = current_page === last_page
            ? '<span class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-500 cursor-not-allowed">Далі</span>'
            : `<a href="#" class="ajax-page-link rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700" data-page="${current_page + 1}">Далі</a>`;

        container.innerHTML = `
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3">
                <p class="text-sm text-slate-400">Показано ${from}–${to} із ${total} записів</p>
                <div class="flex flex-wrap items-center gap-1">
                    ${backBtn}
                    ${elements.join('')}
                    ${nextBtn}
                </div>
            </div>`;

        container.querySelectorAll('.ajax-page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt(link.dataset.page, 10);
                this.load();
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
    }

    bindSortHeaders() {
        const headers = document.querySelectorAll(`#${this.tableId} thead th[data-col-index]`);
        headers.forEach(th => {
            const sortBtn = th.querySelector('[data-action="sort"]');
            if (!sortBtn) return;

            sortBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const colIndex = parseInt(th.dataset.colIndex, 10);
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
                this.load();

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
            const idx = parseInt(th.dataset.colIndex, 10);
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
                const colIndex = parseInt(th.dataset.colIndex, 10);
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
                    <button type="button" id="${this.tableId}FilterSelectAll" class="flex-1 rounded-lg border border-slate-800 bg-slate-950/80 px-2 py-1.5 text-xs text-slate-300 hover:border-indigo-500 hover:text-indigo-300 transition">
                        Вибрати все
                    </button>
                    <button type="button" id="${this.tableId}FilterDeselectAll" class="flex-1 rounded-lg border border-slate-800 bg-slate-950/80 px-2 py-1.5 text-xs text-slate-300 hover:border-indigo-500 hover:text-indigo-300 transition">
                        Зняти все
                    </button>
                </div>
                <div class="relative">
                    <input type="search" id="${this.tableId}FilterSearch" placeholder="Шукати..." class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs text-slate-100 placeholder-slate-500 outline-none focus:border-indigo-500">
                </div>
                <div id="${this.tableId}FilterCheckboxList" class="max-h-60 overflow-y-auto space-y-0.5 pr-1"></div>
            </div>
            <div class="flex items-center justify-between p-3 border-t border-slate-800">
                <button type="button" id="${this.tableId}FilterClear" class="rounded-lg border border-slate-800 bg-slate-950/80 px-3 py-1.5 text-xs text-slate-300 hover:border-rose-500 hover:text-rose-300 transition">
                    Очистити фільтр
                </button>
                <div class="flex gap-2">
                    <button type="button" id="${this.tableId}FilterCancel" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-400 hover:text-white transition">
                        Скасувати
                    </button>
                    <button type="button" id="${this.tableId}FilterApply" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 transition">
                        Застосувати
                    </button>
                </div>
            </div>
        `;

        // Bind dropdown events
        document.getElementById(`${this.tableId}FilterClose`).addEventListener('click', () => this.closeFilterDropdown());
        document.getElementById(`${this.tableId}FilterCancel`).addEventListener('click', () => this.closeFilterDropdown());
        document.getElementById(`${this.tableId}FilterApply`).addEventListener('click', () => this.applyFilterDropdown());
        document.getElementById(`${this.tableId}FilterClear`).addEventListener('click', () => this.clearFilterDropdown());
        document.getElementById(`${this.tableId}FilterSelectAll`).addEventListener('click', () => this.selectAllFilterCheckboxes());
        document.getElementById(`${this.tableId}FilterDeselectAll`).addEventListener('click', () => this.deselectAllFilterCheckboxes());

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

        // Update title
        const titleEl = document.getElementById(`${this.tableId}FilterTitle`);
        if (titleEl) {
            const th = document.querySelector(`#${this.tableId} th[data-col-index="${colIndex}"] .col-title`);
            titleEl.textContent = th ? th.textContent.trim() : 'Фільтр';
        }

        // Fetch distinct values from server
        this.loadDistinctValues(colIndex, columnName, button);
    }

    async loadDistinctValues(colIndex, columnName, button) {
        const dropdown = document.getElementById(`${this.tableId}FilterDropdown`);
        const listEl = document.getElementById(`${this.tableId}FilterCheckboxList`);
        const searchInput = document.getElementById(`${this.tableId}FilterSearch`);
        if (!dropdown || !listEl) return;

        // Show loading state
        listEl.innerHTML = '<div class="py-4 text-center text-xs text-slate-500">Завантаження...</div>';
        if (searchInput) searchInput.value = '';

        // Position dropdown near button
        const rect = button.getBoundingClientRect();
        dropdown.style.top = Math.min(rect.bottom + 4, window.innerHeight - 400) + 'px';
        dropdown.style.left = Math.min(rect.left, window.innerWidth - 300) + 'px';
        dropdown.classList.remove('hidden');

        // Fetch distinct values
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
        const selected = this.columnFilters[colIndex] || new Set();
        const totalValues = values.length;

        // Initialize empty filter set if not exists
        if (!this.columnFilters[colIndex]) {
            this.columnFilters[colIndex] = new Set();
        }

        if (values.length === 0) {
            listEl.innerHTML = '<div class="py-4 text-center text-xs text-slate-500">Немає значень для відображення</div>';
            return;
        }

        let html = '';
        for (const value of values) {
            const isChecked = selected.has(value) ? 'checked' : '';
            const escapedValue = value.replace(/'/g, "\\'").replace(/"/g, '&quot;');
            html += `
                <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-slate-800/50 cursor-pointer filter-checkbox-item">
                    <input type="checkbox" value="${escapedValue}" ${isChecked} class="rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0 filter-checkbox">
                    <span class="text-xs text-slate-300 truncate max-w-full">${value}</span>
                </label>`;
        }

        listEl.innerHTML = html;
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

    selectAllFilterCheckboxes() {
        const checkboxes = document.querySelectorAll(`#${this.tableId}FilterDropdown .filter-checkbox`);
        checkboxes.forEach(cb => cb.checked = true);
    }

    deselectAllFilterCheckboxes() {
        const checkboxes = document.querySelectorAll(`#${this.tableId}FilterDropdown .filter-checkbox`);
        checkboxes.forEach(cb => cb.checked = false);
    }

    applyFilterDropdown() {
        const colIndex = this.activeFilterColIndex;
        if (colIndex === null) return;

        const checkboxes = document.querySelectorAll(`#${this.tableId}FilterDropdown .filter-checkbox:checked`);
        const selectedValues = new Set();
        checkboxes.forEach(cb => selectedValues.add(cb.value));

        // Only store if there are selections (empty means no filter)
        if (selectedValues.size > 0) {
            this.columnFilters[colIndex] = selectedValues;
        } else {
            delete this.columnFilters[colIndex];
        }

        // Update filter button icon to show active filter
        this.updateFilterButtons();

        this.closeFilterDropdown();
        this.currentPage = 1;
        this.load();
    }

    clearFilterDropdown() {
        const colIndex = this.activeFilterColIndex;
        if (colIndex !== null) {
            delete this.columnFilters[colIndex];
            this.updateFilterButtons();
        }
        this.closeFilterDropdown();
        this.currentPage = 1;
        this.load();
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
            const colIndex = parseInt(th.dataset.colIndex, 10);
            const filterBtn = th.querySelector('[data-action="filter"]');
            if (!filterBtn) return;

            const hasFilter = this.columnFilters[colIndex] && this.columnFilters[colIndex].size > 0;

            // Toggle an active indicator on the filter button
            if (hasFilter) {
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
        this.load();
    }
}
