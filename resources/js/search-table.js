import { AjaxTable } from './ajax-table.js';
import { escapeHtml } from './html.js';

export function renderSearchRow(row) {
    const id = escapeHtml(row.id);
    const fullName = escapeHtml(row.full_name);
    const rank = escapeHtml(row.rank);
    const award = escapeHtml(row.award);
    const orderDisplay = escapeHtml(row.order_display);
    const orderPath = encodeURIComponent(String(row.order_id ?? ''));

    return `
        <tr class="hover:bg-slate-800/50" data-row="${id}">
            <td class="px-4 py-3 text-slate-100" data-value="${fullName}">${fullName}</td>
            <td class="px-4 py-3 text-slate-300" data-value="${rank}">${rank}</td>
            <td class="px-4 py-3 text-slate-300" data-value="${award}">${award}</td>
            <td class="px-4 py-3" data-value="${orderDisplay}"><a href="/orders/${orderPath}" class="text-indigo-300 hover:text-indigo-200 hover:underline">${orderDisplay}</a></td>
        </tr>`;
}

globalThis.document?.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('searchForm');
    const searchInput = searchForm?.querySelector('input[name="q"]');
    const currentQuery = searchInput?.value || '';

    let ajaxTable;

    if (currentQuery) {
        ajaxTable = new AjaxTable('searchResultsTable', {
            url: '/search/json',
            paginationSelector: '#searchResultsTable-pagination',
            emptyMessageSelector: '#searchResultsTable-empty-msg',
            loadingSelector: '#searchResultsTable-loading',
            defaultParams: { q: currentQuery },
            renderRow: renderSearchRow,
        });
        void ajaxTable.initialize();
    }

    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const q = searchInput?.value || '';

            if (ajaxTable) {
                ajaxTable.defaultParams = q ? { q } : {};
                ajaxTable.currentPage = 1;
                void ajaxTable.load();
            } else if (q) {
                ajaxTable = new AjaxTable('searchResultsTable', {
                    url: '/search/json',
                    paginationSelector: '#searchResultsTable-pagination',
                    emptyMessageSelector: '#searchResultsTable-empty-msg',
                    loadingSelector: '#searchResultsTable-loading',
                    defaultParams: { q },
                    renderRow: renderSearchRow,
                });
                void ajaxTable.initialize();
            }
        });
    }
});
