/**
 * Monitoring page interaction logic
 */
document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editModal');
    const editForm = document.getElementById('editForm');
    if (!editModal || !editForm) return;

    const editSheet = document.getElementById('edit_sheet');
    const editFullName = document.getElementById('edit_full_name');
    const editSubmissionDate = document.getElementById('edit_submission_date');
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
        const award = btn.dataset.award || '';

        editForm.action = '/monitoring/' + id;
        if (editSheet) editSheet.value = sheet;
        if (editFullName) editFullName.value = fullName;
        if (editSubmissionDate) editSubmissionDate.value = submissionDate;
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
