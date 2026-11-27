import { AjaxTable } from './ajax-table.js';
import { escapeHtml } from './html.js';

export function renderOrderRow(row) {
    const id = escapeHtml(row.order_id);
    const orderPath = encodeURIComponent(String(row.order_id ?? ''));
    const number = escapeHtml(row.number);
    const title = escapeHtml(row.title);
    const awardeesCount = escapeHtml(row.awardees_count);
    const parsedAtSort = escapeHtml(row.parsed_at_sort);
    const parsedAt = escapeHtml(row.parsed_at);
    const trashedClass = row.trashed ? 'opacity-50' : '';
    const lineThrough = row.trashed ? 'line-through' : '';

    return `
        <tr class="hover:bg-slate-800/50 ${trashedClass}" data-row="${id}">
            <td class="px-4 py-3 text-indigo-300 ${lineThrough}" data-value="${number}">${number}</td>
            <td class="px-4 py-3 text-slate-100 ${lineThrough}" data-value="${title}">${title}</td>
            <td class="px-4 py-3 text-slate-300" data-value="${awardeesCount}">${awardeesCount}</td>
            <td class="px-4 py-3 text-slate-300" data-value="${parsedAtSort}">${parsedAt}</td>
            <td class="px-4 py-3">
                <a href="/orders/${orderPath}" class="text-indigo-300 hover:text-indigo-200">Деталі</a>
            </td>
        </tr>`;
}

globalThis.document?.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('ordersTable')) {
        return;
    }

    const table = new AjaxTable('ordersTable', {
        url: '/orders/json',
        paginationSelector: '#ordersTable-pagination',
        emptyMessageSelector: '#ordersTable-empty',
        loadingSelector: '#ordersTable-loading',
        countSelector: '#ordersTable-count',
        defaultSort: 'parsed_at',
        defaultDirection: 'desc',
        filterColumnMap: {
            0: 'number',
        },
        renderRow: renderOrderRow,
        getSortColumnMap() {
            return {
                0: 'number',
                1: 'title',
                2: 'awardees_count',
                3: 'parsed_at',
            };
        },
    });

    const resetBtn = document.getElementById('ordersResetFiltersBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            table.resetAllFilters();
            void table.load();
        });
    }

    const updateResetFiltersButton = () => {
        const hasFilters = Object.keys(table.columnFilters).length > 0;
        if (resetBtn) {
            resetBtn.style.display = hasFilters ? 'inline-flex' : 'none';
        }
    };

    const originalRenderComplete = table.renderComplete;
    table.renderComplete = function () {
        if (originalRenderComplete) originalRenderComplete.apply(this, arguments);
        updateResetFiltersButton();
    };
});
