import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

class Form {
    constructor(submitListener, confirmMessage = 'Delete this item?') {
        this.dataset = confirmMessage ? { confirm: confirmMessage } : {};
        this.isConnected = true;
        this.submissions = 0;
        this.submitListener = submitListener;
        this.methodOverride = 'DELETE';
    }

    hasAttribute(name) {
        return name === 'data-confirm' && Object.hasOwn(this.dataset, 'confirm');
    }

    querySelector(selector) {
        return selector === 'input[name="_method"]' ? { value: this.methodOverride } : null;
    }

    requestSubmit(submitter) {
        this.submissions++;
        this.submitListener({
            target: this,
            submitter,
            preventDefault() {
                throw new Error('Confirmed forms should submit without reopening the dialog.');
            },
        });
    }
}

function createDialog() {
    const documentListeners = {};
    const dialogListeners = {};
    const cancelListeners = {};
    const confirmListeners = {};
    const message = { textContent: '' };
    const dialog = {
        open: false,
        addEventListener: (type, listener) => { dialogListeners[type] = listener; },
        close() {
            this.open = false;
            dialogListeners.close();
        },
        querySelector(selector) {
            return selector === '[data-confirm-cancel]'
                ? { addEventListener: (type, listener) => { cancelListeners[type] = listener; } }
                : { addEventListener: (type, listener) => { confirmListeners[type] = listener; } };
        },
        showModal() {
            this.open = true;
        },
    };
    const elements = {
        confirmationDialog: dialog,
        'confirmationDialog-message': message,
    };
    const document = {
        addEventListener(type, listener) {
            if (type === 'DOMContentLoaded') {
                listener();
            } else {
                documentListeners[type] = listener;
            }
        },
        getElementById: (id) => elements[id],
    };
    class Element {
        constructor(submitter) {
            this.submitter = submitter;
        }

        closest() {
            return this.submitter;
        }
    }
    class HTMLButtonElement {
        constructor(form) {
            this.form = form;
        }
    }
    const context = vm.createContext({
        document,
        Element,
        HTMLButtonElement,
        HTMLFormElement: Form,
        HTMLInputElement: class HTMLInputElement {},
    });
    const source = readFileSync('public/js/confirmation-dialog.js', 'utf8');
    vm.runInContext(source, context);

    return {
        cancel() {
            cancelListeners.click();
        },
        confirm() {
            confirmListeners.click();
        },
        clickSubmit(form) {
            let prevented = false;
            documentListeners.click({
                target: new Element(new HTMLButtonElement(form)),
                preventDefault() {
                    prevented = true;
                },
            });
            return prevented;
        },
        dialog,
        message,
        submit(form) {
            let prevented = false;
            documentListeners.submit({
                target: form,
                submitter: null,
                preventDefault() {
                    prevented = true;
                },
            });
            return prevented;
        },
    };
}

test('confirmation dialog cancels destructive submissions', () => {
    const confirmation = createDialog();
    const form = new Form((event) => confirmation.submit(event.target));

    assert.equal(confirmation.submit(form), true);
    assert.equal(confirmation.dialog.open, true);
    assert.equal(confirmation.message.textContent, 'Delete this item?');

    confirmation.cancel();

    assert.equal(confirmation.dialog.open, false);
    assert.equal(form.submissions, 0);
});

test('confirmation dialog submits the pending form after approval', () => {
    const confirmation = createDialog();
    const form = new Form((event) => confirmation.submit(event.target));

    confirmation.submit(form);
    confirmation.confirm();

    assert.equal(confirmation.dialog.open, false);
    assert.equal(form.submissions, 1);
});

test('confirmation dialog intercepts dynamically rendered delete buttons without confirmation attributes', () => {
    const confirmation = createDialog();
    const form = new Form((event) => confirmation.submit(event.target), null);

    assert.equal(confirmation.clickSubmit(form), true);
    assert.equal(confirmation.dialog.open, true);
    assert.equal(confirmation.message.textContent, 'Ви впевнені, що хочете продовжити?');

    confirmation.confirm();

    assert.equal(confirmation.dialog.open, false);
    assert.equal(form.submissions, 1);
});