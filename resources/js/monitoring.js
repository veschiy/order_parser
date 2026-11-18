/**
 * Monitoring page interaction logic
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-monitoring-export]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const ids = Array.from(document.querySelectorAll('#monitoringTable tbody tr[data-row]'))
                .filter(row => row.style.display !== 'none')
                .map(row => Number(row.dataset.row));
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = link.href;
            for (const [name, value] of [['_token', link.dataset.token], ['record_ids', JSON.stringify(ids)]]) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
            form.remove();
        });
    });
    const editModal = document.getElementById('editModal');
    const editForm = document.getElementById('editForm');
    if (!editModal || !editForm) return;

    const editSheet = document.getElementById('edit_sheet');
    const editFullName = document.getElementById('edit_full_name');
    const editSubmissionDate = document.getElementById('edit_submission_date');
    const editRank = document.getElementById('edit_rank');
    const editAward = document.getElementById('edit_award');

    // Handle open edit modal via data attributes
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-edit-monitoring]');
        if (!btn) return;

        e.preventDefault();
        const id = btn.dataset.id;
        const sheet = btn.dataset.sheet || '';
        const fullName = btn.dataset.fullName || '';
        const submissionDate = btn.dataset.submissionDate || '';
        const rank = btn.dataset.rank || '';
        const award = btn.dataset.award || '';

        editForm.action = '/monitoring/' + id;
        if (editSheet) editSheet.value = sheet;
        if (editFullName) editFullName.value = fullName;
        if (editSubmissionDate) editSubmissionDate.value = submissionDate;
        if (editRank) editRank.value = rank;
        if (editAward) editAward.value = award;

        editModal.classList.remove('hidden');
        editModal.classList.add('flex');
    });

    // Handle close edit modal
    document.querySelectorAll('[data-close-modal="editModal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        });
    });

    // Close on backdrop click
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
        }
    });
});
