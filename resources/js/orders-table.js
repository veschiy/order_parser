import { AjaxTable } from './ajax-table.js';

document.addEventListener('DOMContentLoaded', () => {
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
        renderRow(row) {
            const trashedClass = row.trashed ? 'opacity-50' : '';
            const lineThrough = row.trashed ? 'line-through' : '';
            return `
                <tr class="hover:bg-slate-800/50 ${trashedClass}" data-row="${row.order_id}">
                    <td class="px-4 py-3 text-indigo-300 ${lineThrough}" data-value="${row.number}">${row.number}</td>
                    <td class="px-4 py-3 text-slate-100 ${lineThrough}" data-value="${row.title}">${row.title}</td>
                    <td class="px-4 py-3 text-slate-300" data-value="${row.awardees_count}">${row.awardees_count}</td>
                    <td class="px-4 py-3 text-slate-300" data-value="${row.parsed_at_sort}">${row.parsed_at}</td>
                    <td class="px-4 py-3">
                        <a href="/orders/${row.order_id}" class="text-indigo-300 hover:text-indigo-200">Деталі</a>
                    </td>
                </tr>`;
        },
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
            table.load();
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
