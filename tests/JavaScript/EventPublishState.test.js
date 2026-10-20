const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../media/js/event-publish-state.js'), 'utf8');

function createForm(allowedCategories, selectedCategories) {
    const listeners = {};
    const categories = {
        selectedOptions: selectedCategories.map(value => ({ value: String(value) })),
        addEventListener(event, listener) {
            listeners[event] = listener;
        },
    };
    const state = { disabled: true, value: '1' };
    const document = {
        readyState: 'complete',
        getElementById(id) {
            return id === 'jform_cats' ? categories : id === 'jform_published' ? state : null;
        },
    };
    const window = {
        Joomla: { getOptions: () => allowedCategories },
    };

    vm.runInNewContext(source, { document, window });

    return {
        state,
        select(values) {
            categories.selectedOptions = values.map(value => ({ value: String(value) }));
            listeners.change();
        },
    };
}

test('new events stay unpublished without a category', () => {
    const form = createForm([20], []);

    assert.equal(form.state.disabled, true);
    assert.equal(form.state.value, '0');
});

test('Acogida enables status and a mixed selection applies the stricter permission', () => {
    const form = createForm([20], []);

    form.select([20]);
    assert.equal(form.state.disabled, false);

    form.state.value = '1';
    form.select([20, 18]);
    assert.equal(form.state.disabled, true);
    assert.equal(form.state.value, '0');

    form.select([20]);
    assert.equal(form.state.disabled, false);
    assert.equal(form.state.value, '0');
});
