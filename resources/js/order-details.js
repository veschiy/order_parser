/**
 * Order details page interaction logic
 */
document.addEventListener('DOMContentLoaded', () => {
    // Open edit form
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

    // Cancel edit
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

    // Save edit
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

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
            const response = await fetch('/awardees/' + id, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
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
});
