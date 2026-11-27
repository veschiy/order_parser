import { AjaxTable } from './ajax-table.js';

document.addEventListener('DOMContentLoaded', () => {
    const orderId = document.querySelector('#awardeesTable')?.dataset?.orderId;
    if (!orderId) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const searchInput = document.getElementById('awardeesSearchInput');
    const currentSearch = searchInput?.value.trim() || '';
    let awardeeEditInitialized = false;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);

    const table = new AjaxTable('awardeesTable', {
        url: `/orders/${orderId}/awardees/json`,
        paginationSelector: '#awardeesTable-pagination',
        emptyMessageSelector: '#awardeesTable-empty',
        loadingSelector: '#awardeesTable-loading',
        countSelector: '#awardeesTable-count',
        defaultParams: currentSearch ? { q: currentSearch } : {},
        defaultSort: 'id',
        defaultDirection: 'asc',
        filterColumnMap: {
            2: 'award',
        },
        renderRow(row) {
            const rank = row.rank === '—' ? '' : row.rank;

            return `
                <tr class="hover:bg-slate-800/50" data-row="${row.id}">
                    <td class="px-4 py-3 text-slate-100">
                        <span class="awardee-name" data-id="${row.id}">${row.full_name}</span>
                        <button type="button" class="ml-2 rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-indigo-400 hover:text-indigo-300" data-edit-awardee data-id="${row.id}">Редагувати</button>
                        <div class="hidden mt-2 flex gap-2" data-edit-form="${row.id}" style="display:none;">
                            <input type="text" value="${row.full_name}" class="w-full rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-sm text-slate-100 focus:border-indigo-400 focus:outline-none">
                            <button type="button" class="rounded-md bg-indigo-500 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-400" data-save-awardee-edit data-id="${row.id}">Зберегти</button>
                            <button type="button" class="rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800" data-cancel-awardee-edit data-id="${row.id}">Скасувати</button>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-300">
                        <span class="awardee-rank">${escapeHtml(row.rank)}</span>
                        <button type="button" class="ml-2 rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-indigo-400 hover:text-indigo-300" data-edit-awardee-rank data-id="${row.id}">Редагувати</button>
                        <div class="hidden mt-2 flex gap-2" data-edit-rank-form="${row.id}" style="display:none;">
                            <input type="text" maxlength="255" value="${escapeHtml(rank)}" class="w-full rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-sm text-slate-100 focus:border-indigo-400 focus:outline-none">
                            <button type="button" class="rounded-md bg-indigo-500 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-400" data-save-awardee-rank data-id="${row.id}">Зберегти</button>
                            <button type="button" class="rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800" data-cancel-awardee-rank data-id="${row.id}">Скасувати</button>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-300">${row.award}</td>
                </tr>`;
        },
        renderComplete() {
            if (!awardeeEditInitialized) {
                initAwardeeEdit();
                awardeeEditInitialized = true;
            }
        },
        getSortColumnMap() {
            return {
                0: 'full_name',
                1: 'rank',
                2: 'award',
            };
        },
    });

    const searchForm = document.getElementById('awardeesSearchForm');
    const resetLink = document.getElementById('awardeesSearchReset');
    const emptyMessage = document.querySelector('#awardeesTable-empty p');

    if (searchForm) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();

            const query = searchInput?.value.trim() || '';
            table.defaultParams = query ? { q: query } : {};
            table.currentPage = 1;
            table.load();

            resetLink?.classList.toggle('hidden', !query);
            if (emptyMessage) {
                emptyMessage.textContent = query
                    ? `За запитом «${query}» нічого не знайдено.`
                    : 'Список нагороджених ще не розібрано.';
            }
        });
    }

    if (resetLink) {
        resetLink.addEventListener('click', (event) => {
            event.preventDefault();
            if (searchInput) searchInput.value = '';
            table.defaultParams = {};
            table.currentPage = 1;
            table.load();
            resetLink.classList.add('hidden');
            if (emptyMessage) {
                emptyMessage.textContent = 'Список нагороджених ще не розібрано.';
            }
        });
    }

    function initAwardeeEdit() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-edit-awardee-rank]');
            if (!btn) return;

            const cell = btn.closest('td');
            const id = btn.dataset.id;
            const rankSpan = cell.querySelector('.awardee-rank');
            const editForm = cell.querySelector(`[data-edit-rank-form="${id}"]`);

            if (rankSpan) rankSpan.classList.add('hidden');
            btn.classList.add('hidden');
            if (editForm) {
                editForm.classList.remove('hidden');
                editForm.style.display = 'flex';
            }
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-cancel-awardee-rank]');
            if (!btn) return;

            const id = btn.dataset.id;
            const form = document.querySelector(`[data-edit-rank-form="${id}"]`);
            if (!form) return;

            const cell = form.closest('td');
            form.classList.add('hidden');
            form.style.display = 'none';

            const rankSpan = cell.querySelector('.awardee-rank');
            if (rankSpan) rankSpan.classList.remove('hidden');

            const editBtn = cell.querySelector('[data-edit-awardee-rank]');
            if (editBtn) editBtn.classList.remove('hidden');
        });

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-save-awardee-rank]');
            if (!btn) return;

            const id = btn.dataset.id;
            const form = btn.closest('[data-edit-rank-form]');
            if (!form) return;

            const cell = form.closest('td');
            const input = form.querySelector('input');
            const rank = input ? input.value.trim() : '';

            try {
                const response = await fetch('/awardees/' + id, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ rank: rank || null }),
                });
                if (!response.ok) return;

                const data = await response.json();
                const rankSpan = cell.querySelector('.awardee-rank');
                if (rankSpan) {
                    rankSpan.textContent = data.rank || '—';
                    rankSpan.classList.remove('hidden');
                }
                if (input) input.value = data.rank || '';

                form.classList.add('hidden');
                form.style.display = 'none';

                const editBtn = cell.querySelector('[data-edit-awardee-rank]');
                if (editBtn) editBtn.classList.remove('hidden');
            } catch (err) {
                console.error('Error saving awardee rank:', err);
            }
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-edit-awardee]');
            if (!btn) return;
            const cell = btn.closest('td');
            const id = btn.dataset.id;
            const nameSpan = cell.querySelector('.awardee-name');
            const editForm = cell.querySelector(`[data-edit-form="${id}"]`);
            if (nameSpan) nameSpan.classList.add('hidden');
            btn.classList.add('hidden');
            if (editForm) {
                editForm.classList.remove('hidden');
                editForm.style.display = 'flex';
            }
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-cancel-awardee-edit]');
            if (!btn) return;
            const id = btn.dataset.id;
            const form = document.querySelector(`[data-edit-form="${id}"]`);
            if (!form) return;
            const cell = form.closest('td');
            form.classList.add('hidden');
            form.style.display = 'none';
            const nameSpan = cell.querySelector('.awardee-name');
            if (nameSpan) nameSpan.classList.remove('hidden');
            const editBtn = cell.querySelector('[data-edit-awardee]');
            if (editBtn) editBtn.classList.remove('hidden');
        });

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-save-awardee-edit]');
            if (!btn) return;
            const id = btn.dataset.id;
            const form = btn.closest('[data-edit-form]');
            if (!form) return;
            const cell = form.closest('td');
            const input = form.querySelector('input');
            const name = input ? input.value.trim() : '';
            if (!name) return;

            try {
                const response = await fetch('/awardees/' + id, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ full_name: name }),
                });
                if (!response.ok) return;
                const data = await response.json();
                const nameSpan = cell.querySelector('.awardee-name');
                if (nameSpan) {
                    nameSpan.textContent = data.full_name;
                    nameSpan.classList.remove('hidden');
                }
                if (input) input.value = data.full_name;
                form.classList.add('hidden');
                form.style.display = 'none';
                const editBtn = cell.querySelector('[data-edit-awardee]');
                if (editBtn) editBtn.classList.remove('hidden');
            } catch (err) {
                console.error('Error saving awardee:', err);
            }
        });
    }
});
