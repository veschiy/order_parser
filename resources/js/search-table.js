import { AjaxTable } from './ajax-table.js';

document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('searchForm');
    const searchInput = searchForm?.querySelector('input[name="q"]');
    const currentQuery = searchInput?.value || '';

    let ajaxTable;

    const renderRow = (row) => {
        return `
            <tr class="hover:bg-slate-800/50" data-row="${row.id}">
                <td class="px-4 py-3 text-slate-100" data-value="${row.full_name}">${row.full_name}</td>
                <td class="px-4 py-3 text-slate-300" data-value="${row.rank}">${row.rank}</td>
                <td class="px-4 py-3 text-slate-300" data-value="${row.award}">${row.award}</td>
                <td class="px-4 py-3 text-indigo-300" data-value="${row.order_display}">${row.order_display}</td>
            </tr>`;
    };

    if (currentQuery) {
        ajaxTable = new AjaxTable('searchResultsTable', {
            url: '/search/json',
            paginationSelector: '#searchResultsTable-pagination',
            emptyMessageSelector: '#searchResultsTable-empty-msg',
            loadingSelector: '#searchResultsTable-loading',
            defaultParams: { q: currentQuery },
            renderRow,
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const q = searchInput?.value || '';

            if (ajaxTable) {
                ajaxTable.defaultParams = q ? { q } : {};
                ajaxTable.currentPage = 1;
                ajaxTable.load();
            } else if (q) {
                ajaxTable = new AjaxTable('searchResultsTable', {
                    url: '/search/json',
                    paginationSelector: '#searchResultsTable-pagination',
                    emptyMessageSelector: '#searchResultsTable-empty-msg',
                    loadingSelector: '#searchResultsTable-loading',
                    defaultParams: { q },
                    renderRow,
                });
            }
        });
    }
});
