// Run: node --test tests/JavaScript/weekly-recurrence.test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function editor(stored = '', date = '2026-10-14') {
    class Element {
        constructor(tag) { this.tagName = tag; this.children = []; this.listeners = {}; this.style = {}; this.value = ''; }
        appendChild(child) { this.children.push(child); return child; }
        addEventListener(type, fn) { this.listeners[type] = fn; }
        setAttribute(key, value) { this[key] = value; }
        closest() { return this.wrapper || null; }
        get options() { return this.children; }
    }
    const fields = {};
    ['recurrence_byday', 'jform_dates', 'recurrence_output', 'jform_recurrence_type',
        'recurrence_number', 'recurrence_number_saved', 'counter_row'].forEach(id => fields[id] = new Element('input'));
    fields.recurrence_byday.value = stored;
    fields.jform_dates.value = date;
    fields.jform_recurrence_type.value = '2';
    fields.recurrence_number.value = fields.recurrence_number_saved.value = '2';
    const context = vm.createContext({
        window: {}, Joomla: {Text: {_: key => key}},
        document: {getElementById: id => fields[id] || null, createElement: tag => new Element(tag),
            createTextNode: text => ({textContent: text})}
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../media/js/recurrence.js'), 'utf8'), context);
    context.$weekday = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'].map(day => [day, day]);
    function render() {
        const container = context.generateWeeklyDays();
        return {container, select: container.children[1].children[0], hidden: container.children[2]};
    }
    function change(control, selected) {
        control.select.options.forEach(option => option.selected = selected.includes(option.value));
        control.select.listeners.change();
    }
    return {context, fields, render, change};
}

test('legacy weekly selects start weekday, ignores stale monthly days and does not migrate implicitly', () => {
    for (const stored of ['', '0', 'MO,FR']) {
        const {render} = editor(stored);
        const control = render();
        assert.equal(control.hidden.value, 'WE');
        assert.equal(control.hidden.name, undefined);
        assert.deepEqual(control.select.options.filter(option => option.selected).map(option => option.value), ['WE']);
    }
});

test('stored explicit days reload in a Joomla native multiple selector', () => {
    const control = editor('W:1,3,5').render();
    assert.equal(control.container.children[1].tagName, 'joomla-field-fancy-select');
    assert.equal(control.select.multiple, true);
    assert.equal(control.hidden.value, 'MO,WE,FR');
    assert.equal(control.hidden.name, 'recurrence_weekly_days');
});

test('adding, removing and clearing days produces the submitted value without changing monthly field', () => {
    const e = editor('MO,FR');
    const control = e.render();
    e.change(control, ['MO', 'WE', 'FR']);
    assert.equal(control.hidden.value, 'MO,WE,FR');
    assert.equal(control.hidden.name, 'recurrence_weekly_days');
    assert.equal(e.fields.recurrence_byday.value, 'MO,FR');
    e.change(control, ['FR']);
    assert.equal(e.render().hidden.value, 'FR');
    e.change(control, []);
    assert.equal(e.render().hidden.value, ''); // Server must reject, not silently restore a day.
});

test('automatic day follows date, custom day selection does not', () => {
    const e = editor();
    assert.equal(e.render().hidden.value, 'WE');
    e.fields.jform_dates.value = '2026-10-16';
    const control = e.render();
    assert.equal(control.hidden.value, 'FR');
    e.change(control, ['MO', 'FR']);
    e.fields.jform_dates.value = '2026-10-19';
    assert.equal(e.render().hidden.value, 'MO,FR');
});

test('blank and impossible dates do not select a spurious day', () => {
    for (const date of ['', '2026-02-30', 'not a date']) {
        assert.equal(editor('', date).render().hidden.value, '');
    }
});

test('localized dates use Joomla calendar parser and round-trip validation', () => {
    const e = editor('', '14.10.2026');
    e.fields.jform_dates.wrapper = {_joomlaCalendar: {
        params: {dateFormat: '%d.%m.%Y', dateType: 'gregorian'}, strings: {}
    }};
    vm.runInContext("Date.parseFieldDate = function () { return {print: function () { return '14.10.2026'; }, getDay: function () { return 3; }}; };", e.context);
    assert.equal(e.render().hidden.value, 'WE');
    e.fields.jform_dates.value = '31.02.2026';
    assert.equal(e.render().hidden.value, '');
});

test('date change listener only rebuilds automatic weekly selection', () => {
    const e = editor();
    let renders = 0;
    e.context.output_recurrencescript = () => { renders++; };
    e.context.start_recurrencescript('jform_recurrence_type');
    e.fields.jform_dates.listeners.change();
    assert.equal(renders, 2);
    e.change(e.render(), ['MO', 'WE']);
    e.fields.jform_dates.listeners.change();
    assert.equal(renders, 2);
});

test('interval remains selected when the recurrence UI is rebuilt', () => {
    const e = editor();
    e.fields.recurrence_selectlist = {value: '3'};
    e.context.set_parameter();
    assert.equal(e.fields.recurrence_number.value, '3');
    assert.equal(e.fields.recurrence_number_saved.value, '3');
});
