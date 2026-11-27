/**
 * Monitoring page interaction logic
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-monitoring-export]').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            if (link.dataset.exporting === 'true') return;

            const ids = Array.from(document.querySelectorAll('#monitoringTable tbody tr[data-row]'))
                .filter(row => row.style.display !== 'none')
                .map(row => Number(row.dataset.row));

            const label = link.querySelector('[data-export-label]');
            const loading = link.querySelector('[data-export-loading]');
            const status = link.closest('section')?.querySelector('[data-monitoring-export-status]');
            link.dataset.exporting = 'true';
            link.setAttribute('aria-busy', 'true');
            link.classList.add('pointer-events-none', 'opacity-70');
            label?.classList.remove('inline-flex');
            label?.classList.add('hidden');
            loading?.classList.remove('hidden');
            loading?.classList.add('inline-flex');
            if (status) {
                status.textContent = '';
                status.classList.add('hidden');
            }

            try {
                const response = await fetch(link.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: new URLSearchParams({
                        _token: link.dataset.token,
                        record_ids: JSON.stringify(ids),
                    }),
                });

                if (!response.ok) {
                    throw new Error('CSV export request failed');
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const downloadLink = document.createElement('a');
                const filename = /filename="?([^";]+)"?/i.exec(response.headers.get('Content-Disposition') ?? '')?.[1] || 'monitoring_export.csv';
                downloadLink.href = objectUrl;
                downloadLink.download = filename;
                document.body.appendChild(downloadLink);
                downloadLink.click();
                downloadLink.remove();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            } catch (error) {
                console.error('CSV export request failed:', error);
                if (status) {
                    status.textContent = 'Не вдалося експортувати CSV. Спробуйте ще раз.';
                    status.classList.remove('hidden');
                }
            } finally {
                delete link.dataset.exporting;
                link.removeAttribute('aria-busy');
                link.classList.remove('pointer-events-none', 'opacity-70');
                label?.classList.remove('hidden');
                label?.classList.add('inline-flex');
                loading?.classList.add('hidden');
                loading?.classList.remove('inline-flex');
            }
        });
    });
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
