document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('confirmationDialog');
    const confirmationMessage = document.getElementById('confirmationDialog-message');
    const cancelButton = dialog?.querySelector('[data-confirm-cancel]');
    const confirmButton = dialog?.querySelector('[data-confirm-submit]');

    if (!dialog || !confirmationMessage || !cancelButton || !confirmButton) {
        return;
    }

    let pendingForm = null;
    let pendingSubmitter = null;
    let formBeingConfirmed = null;

    const requiresConfirmation = (form) => {
        const methodOverride = form.querySelector('input[name="_method"]')?.value;

        return form.dataset.confirm !== undefined || methodOverride?.toUpperCase() === 'DELETE';
    };

    const showConfirmation = (form, submitter) => {
        if (dialog.open) {
            return;
        }

        pendingForm = form;
        pendingSubmitter = submitter;
        confirmationMessage.textContent = form.dataset.confirm || 'Ви впевнені, що хочете продовжити?';
        dialog.showModal();
    };

    document.addEventListener('click', (event) => {
        const submitter = event.target instanceof Element
            ? event.target.closest('button[type="submit"], input[type="submit"]')
            : null;
        const form = submitter?.form;

        if (!(form instanceof HTMLFormElement) || !requiresConfirmation(form)) {
            return;
        }

        event.preventDefault();
        showConfirmation(form, submitter);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !requiresConfirmation(form) || form === formBeingConfirmed) {
            return;
        }

        event.preventDefault();
        showConfirmation(form, event.submitter);
    });

    const closeDialog = () => {
        pendingForm = null;
        pendingSubmitter = null;

        if (dialog.open) {
            dialog.close();
        }
    };

    cancelButton.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });
    dialog.addEventListener('close', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDialog();
        }
    });

    confirmButton.addEventListener('click', () => {
        const form = pendingForm;
        const submitter = pendingSubmitter;

        closeDialog();

        if (!form?.isConnected) {
            return;
        }

        formBeingConfirmed = form;

        try {
            if ((submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) && submitter.form === form) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
        } finally {
            formBeingConfirmed = null;
        }
    });
});