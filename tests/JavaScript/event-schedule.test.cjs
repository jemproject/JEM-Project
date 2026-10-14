// Run: node --test tests/JavaScript/event-schedule.test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function editor(stored = {version: 1, mode: 'daily', days: []}, start = '2026-10-15', end = '2026-10-17') {
    class Element {
        constructor(tag) { this.tagName = tag; this.children = []; this.listeners = {}; this.dataset = {}; this.value = ''; }
        append(...nodes) { this.children.push(...nodes); }
        replaceChildren(...nodes) { this.children = nodes; }
        addEventListener(type, fn) { this.listeners[type] = fn; }
        setAttribute(key, value) { this[key] = value; }
        closest() { return this.wrapper || null; }
    }
    const form = new Element('form'), root = new Element('div'), hidden = new Element('input'), panel = new Element('div');
    root.wrapper = form;
    hidden.id = 'jform_event_schedule';
    hidden.value = stored === null ? '' : JSON.stringify(stored);
    const labels = Object.fromEntries(['label', 'unconfigured', 'daily', 'continuous', 'per_day', 'closed', 'all_day',
        'start', 'end', 'add', 'remove', 'initialise', 'confirm', 'invalid_dates', 'day', 'range_changed'].map(k => [k, k]));
    root.dataset.labels = JSON.stringify(labels);
    root.querySelector = selector => selector === 'input[type="hidden"]' ? hidden : panel;
    const fields = {};
    for (const [name, value] of Object.entries({'jform[dates]': start, 'jform[enddates]': end,
        starthours: '10', startminutes: '00', endhours: '18', endminutes: '00'})) {
        fields[name] = new Element('input'); fields[name].value = value; fields[name].name = name;
    }
    form.querySelector = selector => fields[selector.match(/\[name="(.+)"\]/)[1]];
    let confirm = true;
    const context = vm.createContext({window: {confirm: () => confirm},
        document: {readyState: 'complete', querySelectorAll: () => [root], addEventListener() {},
            createElement: tag => new Element(tag), createTextNode: text => ({textContent: text})}});
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../media/js/event-schedule.js'), 'utf8'), context);
    const mode = panel.children[0], section = panel.children[1];
    const [fill, message, days] = section.children;
    return {root, hidden, fields, mode, section, fill, message, days, context,
        state: () => hidden.value ? JSON.parse(hidden.value) : null,
        changeMode(value) { mode.value = value; mode.listeners.change(); },
        dateChanged() { form.listeners.change({target: fields['jform[dates]']}); },
        confirm(value) { confirm = value; }};
}

test('new event defaults to daily with an accessible visible selector', () => {
    const e = editor();
    assert.equal(e.mode.value, 'daily');
    assert.equal(e.mode.id, 'jform_event_schedule');
    assert.equal(e.hidden.id, 'jform_event_schedule_value');
    assert.equal(e.section.hidden, true);
});

test('legacy event does not opt in on load or date change; choice is explicit', () => {
    const e = editor(null);
    assert.equal(e.mode.value, '');
    e.dateChanged();
    assert.equal(e.hidden.value, '');
    e.changeMode('daily');
    assert.equal(e.state().mode, 'daily');
    e.changeMode('');
    assert.equal(e.hidden.value, '');
});

test('fill creates one relative day per date and copies the hours', () => {
    const e = editor(); e.changeMode('per_day'); e.fill.listeners.click();
    assert.equal(e.days.children.length, 3);
    assert.deepEqual(e.state().days[2], {offset: 2, closed: false, all_day: false, slots: [{start: '10:00', end: '18:00'}]});
    assert.equal(e.days.children[2].children[0].textContent, '2026-10-17');
});

test('blank dates, invalid end dates and too-long ranges cannot initialise', () => {
    for (const [start, end] of [['', ''], ['2026-02-30', ''], ['2026-10-15', 'invalid'], ['2026-10-15', '2028-10-15']]) {
        const e = editor(undefined, start, end); e.changeMode('per_day'); e.fill.listeners.click();
        assert.equal(e.state().days.length, 0); assert.equal(e.message.textContent, 'invalid_dates');
    }
});

test('changing duration does not silently delete the programme', () => {
    const e = editor(); e.changeMode('per_day'); e.fill.listeners.click();
    e.fields['jform[enddates]'].value = '2026-10-16'; e.dateChanged();
    assert.equal(e.state().days.length, 3); assert.equal(e.message.textContent, 'range_changed');
    e.confirm(false); e.fill.listeners.click(); assert.equal(e.state().days.length, 3);
    e.confirm(true); e.fill.listeners.click(); assert.equal(e.state().days.length, 2);
});

test('shifting dates preserves offsets and sessions', () => {
    const e = editor(); e.changeMode('per_day'); e.fill.listeners.click(); const before = e.hidden.value;
    e.fields['jform[dates]'].value = '2026-10-22'; e.fields['jform[enddates]'].value = '2026-10-24'; e.dateChanged();
    assert.equal(e.hidden.value, before); assert.equal(e.days.children[0].children[0].textContent, '2026-10-22');
});

test('closed and all-day flags are exclusive and serialize without sessions', () => {
    const e = editor(); e.changeMode('per_day'); e.fill.listeners.click();
    let controls = e.days.children[0].children[1];
    let closed = controls.children[0].children[0]; closed.checked = true; closed.listeners.change();
    assert.deepEqual(e.state().days[0], {offset: 0, closed: true, all_day: false, slots: []});
    controls = e.days.children[0].children[1];
    let allDay = controls.children[1].children[0]; allDay.checked = true; allDay.listeners.change();
    assert.deepEqual(e.state().days[0], {offset: 0, closed: false, all_day: true, slots: []});
});

test('time edits, add and remove serialize to the submitted JSON', () => {
    const e = editor(); e.changeMode('per_day'); e.fill.listeners.click();
    let day = e.days.children[0]; let row = day.children[2];
    let start = row.children[0].children[0]; start.value = '09:15'; start.listeners.change();
    assert.equal(e.state().days[0].slots[0].start, '09:15');
    day.children.at(-1).listeners.click(); assert.equal(e.state().days[0].slots.length, 2);
    e.days.children[0].children[2].children[2].listeners.click(); assert.equal(e.state().days[0].slots.length, 1);
});

test('all-day and midnight selectors match server interpretation', () => {
    const e = editor(); e.changeMode('per_day');
    e.fields.starthours.value = ''; e.fields.startminutes.value = ''; e.fill.listeners.click();
    assert.equal(e.state().days[0].all_day, true);
    e.fields.startminutes.value = '30'; e.fill.listeners.click();
    assert.equal(e.state().days[0].slots[0].start, '00:30');
});

test('Joomla localised dates are round-trip checked before filling', () => {
    const e = editor(undefined, '15.10.2026', '17.10.2026');
    const wrapper = {_joomlaCalendar: {params: {dateFormat: '%d.%m.%Y', dateType: 'gregorian'}, strings: {}}};
    e.fields['jform[dates]'].wrapper = e.fields['jform[enddates]'].wrapper = wrapper;
    vm.runInContext(`Date.parseFieldDate = value => ({
        print: () => value === '31.02.2026' ? '03.03.2026' : value,
        getFullYear: () => 2026, getMonth: () => 9, getDate: () => Number(value.slice(0, 2))
    });`, e.context);
    e.changeMode('per_day'); e.fill.listeners.click(); assert.equal(e.state().days.length, 3);
    e.fields['jform[dates]'].value = '31.02.2026'; e.dateChanged();
    assert.equal(e.message.textContent, 'invalid_dates');
});
