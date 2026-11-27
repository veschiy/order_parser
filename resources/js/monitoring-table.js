import { AjaxTable } from './ajax-table.js';
import { escapeHtml } from './html.js';

export function renderMonitoringRow(row, csrfToken = '') {
    const id = escapeHtml(row.id);
    const sheet = escapeHtml(row.sheet || '—');
    const sheetValue = escapeHtml(row.sheet);
    const rank = escapeHtml(row.rank || '—');
    const rankValue = escapeHtml(row.rank);
    const fullName = escapeHtml(row.full_name);
    const submissionDate = escapeHtml(row.submission_date);
    const submissionDateDisplay = escapeHtml(row.submission_date_display);
    const award = escapeHtml(row.award || '—');
    const awardValue = escapeHtml(row.award);
    const score = escapeHtml(row.score);
    const matchesSummary = escapeHtml(row.matchesSummary);
    const escapedCsrfToken = escapeHtml(csrfToken);
    const scoreBadge = getScoreBadge(row.score);
    const matchesHtml = renderMatches(row);

    return `
        <tr class="hover:bg-slate-800/40 monitoring-row" id="row-${id}" data-row="${id}">
            <td class="px-1.5 py-2 text-slate-300" data-col-index="0" data-col="sheet" data-value="${sheetValue}">
                ${sheet}
            </td>
            <td class="px-1.5 py-2 text-slate-300" data-col-index="1" data-col="rank" data-value="${rankValue}">
                ${rank}
            </td>
            <td class="px-1.5 py-2 font-bold text-slate-100" data-col-index="2" data-col="full_name" data-value="${fullName}">
                <div class="flex items-center gap-2">
                    <span>${fullName}</span>
                </div>
            </td>
            <td class="px-1.5 py-2 text-slate-300" data-col-index="3" data-col="submission_date" data-value="${submissionDate}">
                ${submissionDateDisplay}
            </td>
            <td class="px-1.5 py-2 text-slate-300" data-col-index="4" data-col="award" data-value="${awardValue}">
                ${award}
            </td>
            <td class="px-1.5 py-2 text-center" data-col-index="5" data-col="score" data-value="${score}">
                ${scoreBadge}
            </td>
            <td class="px-1.5 py-2 text-xs" data-col="matches" data-value="${matchesSummary}">
                ${matchesHtml}
            </td>
            <td class="px-1.5 py-2 text-right">
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        data-edit-monitoring
                        data-id="${id}"
                        data-sheet="${sheetValue}"
                        data-full-name="${fullName}"
                        data-submission-date="${submissionDate}"
                        data-rank="${rankValue}"
                        data-award="${awardValue}"
                        class="rounded-lg border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-indigo-400 hover:text-indigo-300 transition"
                    >
                        Редагувати
                    </button>
                    <form method="POST" action="/monitoring/${id}" data-confirm="Видалити цей запис?" class="inline">
                        <input type="hidden" name="_token" value="${escapedCsrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="rounded-lg border border-rose-900/60 px-2 py-1 text-xs text-rose-400 hover:bg-rose-950/50">
                            Видалити
                        </button>
                    </form>
                </div>
            </td>
        </tr>`;
}

globalThis.document?.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('monitoringTable')) {
        return;
    }

    const searchInput = document.getElementById('monitoringSearchInput');
    const currentSearch = searchInput?.value || '';

    const ajaxTable = new AjaxTable('monitoringTable', {
        url: '/monitoring/json',
        paginationSelector: '#monitoringTable-pagination',
        emptyMessageSelector: '#monitoringTable-empty',
        loadingSelector: '#monitoringTable-loading',
        countSelector: '#monitoringTable-count',
        defaultParams: currentSearch ? { q: currentSearch } : {},
        filterColumnMap: {
            0: 'sheet',
            1: 'rank',
            2: 'full_name',
            3: 'submission_date',
            4: 'award',
            5: 'score',
            6: 'matches',
        },
        renderRow(row) {
            const csrfToken = document.querySelector('meta[name=csrf-token]')?.content || '';

            return renderMonitoringRow(row, csrfToken);
        },
        renderComplete() {
            initMonitoringEditModal();
        },
        getSortColumnMap() {
            return {
                0: 'sheet',
                1: 'rank',
                2: 'full_name',
                3: 'submission_date',
                4: 'award',
                5: 'score',
                6: 'matches',
            };
        },
    });
    void ajaxTable.initialize();

    const searchForm = document.getElementById('monitoringSearchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const q = searchInput?.value || '';
            ajaxTable.defaultParams = q ? { q } : {};
            ajaxTable.currentPage = 1;
            void ajaxTable.load();

            const resetLink = document.getElementById('monitoringSearchReset');
            if (resetLink) {
                resetLink.classList.toggle('hidden', !q);
            }
        });
    }

    const resetLink = document.getElementById('monitoringSearchReset');
    if (resetLink) {
        resetLink.addEventListener('click', (e) => {
            e.preventDefault();
            if (searchInput) searchInput.value = '';
            ajaxTable.defaultParams = {};
            ajaxTable.currentPage = 1;
            void ajaxTable.load();
            resetLink.classList.add('hidden');
        });
    }

    // Reset all filters button (also clears search)
    const resetFiltersBtn = document.getElementById('monitoringResetFiltersBtn');
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            ajaxTable.defaultParams = {};
            ajaxTable.resetAllFilters();
            if (resetLink) resetLink.classList.add('hidden');
            updateResetFiltersButton();
        });
    }

    const updateResetFiltersButton = () => {
        const hasFilters = Object.keys(ajaxTable.columnFilters).length > 0;
        if (resetFiltersBtn) {
            resetFiltersBtn.style.display = hasFilters ? 'inline-flex' : 'none';
        }
    };

    // Override renderComplete to also update the reset button visibility
    const originalRenderComplete = ajaxTable.renderComplete;
    ajaxTable.renderComplete = function () {
        if (originalRenderComplete) originalRenderComplete.apply(this, arguments);
        updateResetFiltersButton();
    };

    // Initial visibility check
    updateResetFiltersButton();
});

function getScoreBadge(score) {
    if (score === 0) {
        return '<span class="inline-flex items-center rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-400" title="ПІБ не знайдено в указах (0 балів)">0</span>';
    }
    if (score === 1) {
        return '<span class="inline-flex items-center rounded-full bg-amber-950/80 border border-amber-700/60 px-2.5 py-1 text-xs font-semibold text-amber-300" title="Збіг за ПІБ (+1 бал)">1</span>';
    }
    if (score === 2) {
        return '<span class="inline-flex items-center rounded-full bg-blue-950/80 border border-blue-700/60 px-2.5 py-1 text-xs font-semibold text-blue-300" title="Збіг за ПІБ + одна з характеристик (+2 бали)">2</span>';
    }
    if (score === 3) {
        return '<span class="inline-flex items-center rounded-full bg-purple-950/80 border border-purple-700/60 px-2.5 py-1 text-xs font-semibold text-purple-300" title="ПІБ + дві з характеристик (+3 бали)">3</span>';
    }
    return `<span class="inline-flex items-center rounded-full bg-emerald-950/80 border border-emerald-600/60 px-2.5 py-1 text-xs font-bold text-emerald-300" title="ПІБ + звання + Нагорода + Дата (максимум, +4 бали)">${escapeHtml(score)}</span>`;
}

function renderMatches(row) {
    if (!row.matches || row.matches.length === 0) {
        return '<span class="text-slate-500">Не знайдено</span>';
    }

    let html = '<div class="space-y-1">';
    for (const match of row.matches) {
        const orderId = escapeHtml(match.order_id);
        const orderDisplay = escapeHtml(match.order_display);
        const matchScore = escapeHtml(match.score);
        const award = escapeHtml(match.award);
        const orderLink = match.order_id
            ? `<a href="/orders/${orderId}" class="font-medium text-indigo-300 hover:underline">${orderDisplay}</a>`
            : `<span class="text-slate-300">${orderDisplay}</span>`;

        let badges = '<span class="rounded px-1.5 py-0.5 bg-indigo-900/40 text-indigo-300">ПІБ ✓</span>';
        if (match.rankMatched) badges += '<span class="rounded px-1.5 py-0.5 bg-purple-900/40 text-purple-300">Звання ✓</span>';
        if (match.awardMatched) badges += '<span class="rounded px-1.5 py-0.5 bg-emerald-900/40 text-emerald-300">Нагорода ✓</span>';
        if (match.dateMatched) badges += '<span class="rounded px-1.5 py-0.5 bg-blue-900/40 text-blue-300">Дата протягом 6 місяців після подання ✓</span>';

        html += `
            <div class="rounded-lg bg-slate-950/60 px-1.5 py-1.5 border border-slate-800">
                <div class="flex items-center justify-between gap-2">
                    ${orderLink}
                    <span class="text-[11px] font-semibold text-slate-300 bg-slate-800 px-1.5 py-0.5 rounded">бал: ${matchScore}</span>
                </div>
                <div class="mt-0.5 text-slate-400">${award}</div>
                <div class="mt-1 flex flex-wrap gap-1 text-[10px]">${badges}</div>
            </div>`;
    }
    html += '</div>';
    return html;
}

function initMonitoringEditModal() {
    const editModal = document.getElementById('editModal');
    const editForm = document.getElementById('editForm');
    if (!editModal || !editForm) return;

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-edit-monitoring]');
        if (!btn) return;
        e.preventDefault();
        editForm.action = '/monitoring/' + btn.dataset.id;
        document.getElementById('edit_full_name').value = btn.dataset.fullName || '';
        document.getElementById('edit_sheet').value = btn.dataset.sheet || '';
        document.getElementById('edit_submission_date').value = btn.dataset.submissionDate || '';
        document.getElementById('edit_rank').value = btn.dataset.rank || '';
        document.getElementById('edit_award').value = btn.dataset.award || '';
        editModal.classList.remove('hidden');
        editModal.classList.add('flex');
    });

    document.querySelectorAll('[data-close-modal="editModal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        });
    });

    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }
    });
}
