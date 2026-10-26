import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

class Element {
    constructor() {
        this.listeners = {};
        this.dataset = {};
        this.hidden = false;
        this.textContent = '';
        this.classList = {add() {}, remove() {}};
    }

    addEventListener(type, callback) {
        (this.listeners[type] ??= []).push(callback);
    }

    dispatch(type) {
        const event = {
            target: this,
            defaultPrevented: false,
            preventDefault() { this.defaultPrevented = true; }
        };
        for (const callback of this.listeners[type] ?? []) {
            callback(event);
        }
        return event;
    }

    focus() {
        this.focused = true;
    }
}

const file = new Element();
const form = new Element();
const name = new Element();
const action = new Element();
const confirmation = new Element();
const confirmLabel = new Element();
const dialog = new Element();
const message = new Element();
const status = new Element();
const accept = new Element();
const cancel = new Element();
const rename = new Element();
const control = new Element();

file.files = [];
name.value = '';
action.value = '';
action.options = [
    {text: 'Choose an option'},
    {text: 'Use the existing image'},
    {text: 'Save a copy'},
    {text: 'Replace the existing image'}
];
action.selectedIndex = 0;
confirmation.closest = () => confirmLabel;
dialog.open = false;
dialog.showModal = () => { dialog.open = true; };
dialog.close = () => { dialog.open = false; };
dialog.querySelector = (selector) => ({
    '[data-jem-image-accept]': accept,
    '[data-jem-image-cancel]': cancel,
    '[data-jem-image-rename]': rename
})[selector];
form.querySelector = () => null;
control.closest = () => form;
control.querySelector = (selector) => ({
    'input[name^="jem_image_name["]': name,
    'select[name^="jem_image_conflict["]': action,
    'input[name^="jem_image_replace_confirm["]': confirmation,
    '.jem-image-collision-dialog': dialog,
    '.jem-image-collision-message': message,
    '.jem-image-name-status': status
})[selector];
Object.assign(control.dataset, {
    jemImageFileId: 'image-file',
    jemImageResource: 'venue',
    jemImagePath: '',
    jemImageRecordId: '0',
    jemImageToken: 'token',
    jemImageCheckUrl: '/check',
    jemImageChecking: 'Checking',
    jemImageAvailable: '%s is available',
    jemImageConflict: '%s already exists',
    jemImageCheckFailed: 'Check failed'
});

let failed = false;
const sandbox = {
    document: {
        readyState: 'complete',
        getElementById: () => file,
        querySelectorAll: () => [control]
    },
    FormData: class { getAll() { return []; } },
    fetch: async (url, options) => {
        assert.equal(url, '/check');
        assert.equal(options.method, 'POST');
        if (failed) {
            throw new Error('Network error');
        }
        const filename = options.body.get('stem') || 'photo';
        return {
            ok: true,
            json: async () => ({
                success: true,
                data: {filename: filename + '.jpg', exists: filename === 'photo'}
            })
        };
    },
    AbortController,
    URLSearchParams,
    setTimeout,
    clearTimeout
};

const source = readFileSync(new URL('../media/js/image-filename-policy.js', import.meta.url), 'utf8');
runInNewContext(source, sandbox);
const flush = () => new Promise((resolve) => setImmediate(resolve));

file.files = [{name: 'photo.jpg'}];
file.dispatch('change');
await flush();
assert.equal(dialog.open, true, 'a duplicate opens the dialog');
assert.equal(status.hidden, true, 'the duplicate message stays inside the dialog');

action.value = 'replace';
action.selectedIndex = 3;
action.dispatch('change');
assert.equal(confirmLabel.hidden, false, 'replacement reveals the confirmation');
accept.dispatch('click');
assert.equal(dialog.open, true, 'replacement cannot continue without confirmation');
confirmation.checked = true;
accept.dispatch('click');
assert.equal(dialog.open, false, 'confirmed replacement closes the dialog');
assert.equal(form.dispatch('submit').defaultPrevented, false);

file.dispatch('change');
await flush();
cancel.dispatch('click');
assert.equal(form.dispatch('submit').defaultPrevented, true, 'cancelled collision blocks submission');
rename.dispatch('click');
assert.equal(name.focused, true);
name.value = 'new_photo';
name.dispatch('input');
await new Promise((resolve) => setTimeout(resolve, 300));
await flush();
assert.equal(dialog.open, false, 'a free renamed filename needs no dialog');
assert.equal(control.dataset.jemImageCheckState, 'available');

failed = true;
file.dispatch('change');
await flush();
assert.equal(control.dataset.jemImageCheckState, 'failed', 'network errors remain visible');

console.log('Image filename dialog: 11 checks passed');

