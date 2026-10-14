/** @package JEM @license GNU/GPL */
(() => {
    'use strict';
    const dateOnly = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        const date = new Date(`${value}T00:00:00Z`);
        return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value ? date : null;
    };
    const initialise = (root) => {
        if (root.dataset.ready) return;
        root.dataset.ready = '1';
        const labels = JSON.parse(root.dataset.labels);
        const hidden = root.querySelector('input[type="hidden"]');
        const editor = root.querySelector('[data-schedule-editor]');
        const form = root.closest('form');
        let state;
        const legacy = hidden.value === '';
        try { state = legacy ? {version: 1, mode: '', days: []} : JSON.parse(hidden.value); } catch (_) { return; }
        if (!state || !Array.isArray(state.days)) return;
        const el = (tag, text, cls) => {
            const node = document.createElement(tag);
            if (text !== undefined) node.textContent = text;
            if (cls) node.className = cls;
            return node;
        };
        const button = (text, action) => {
            const node = el('button', text, 'btn btn-sm btn-outline-secondary');
            node.type = 'button'; node.addEventListener('click', action); return node;
        };
        const save = () => { hidden.value = state.mode ? JSON.stringify(state) : ''; };
        const fieldDate = (name) => {
            const field = form.querySelector(`[name="jform[${name}]"]`);
            const value = field?.value.trim() || '';
            if (!value) return null;
            const calendar = field.closest('.field-calendar')?._joomlaCalendar;
            if (!calendar || typeof Date.parseFieldDate !== 'function') return dateOnly(value);
            const {dateFormat, dateType} = calendar.params;
            const date = Date.parseFieldDate(value, dateFormat, dateType, calendar.strings);
            if (date.print(dateFormat, dateType, true, calendar.strings) !== value) return null;
            return dateOnly(`${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`);
        };
        const dates = () => {
            const start = fieldDate('dates');
            const end = form.querySelector('[name="jform[enddates]"]')?.value.trim() ? fieldDate('enddates') : start;
            if (!start || !end || end < start) return null;
            const count = Math.round((end - start) / 86400000) + 1;
            return count <= 366 ? {start, count} : null;
        };
        const time = (prefix) => {
            const hour = form.querySelector(`[name="${prefix}hours"]`)?.value || '';
            const minute = form.querySelector(`[name="${prefix}minutes"]`)?.value || '';
            return hour === '' && minute === '' ? '' : `${(hour || '0').padStart(2, '0')}:${(minute || '0').padStart(2, '0')}`;
        };
        const mode = el('select', undefined, 'form-select');
        mode.setAttribute('aria-label', labels.label);
        mode.id = hidden.id;
        hidden.id += '_value';
        for (const value of legacy ? ['', 'daily', 'continuous', 'per_day'] : ['daily', 'continuous', 'per_day']) {
            const option = el('option', labels[value || 'unconfigured']); option.value = value; mode.append(option);
        }
        mode.value = state.mode;
        const section = el('div');
        const message = el('p', '', 'form-text'); message.setAttribute('aria-live', 'polite');
        const days = el('div', undefined, 'jem-schedule-days');
        const fill = button(labels.initialise, () => {
            const range = dates();
            if (!range) { message.textContent = labels.invalid_dates; return; }
            if (state.days.length && !window.confirm(labels.confirm)) return;
            const from = time('start'), until = time('end');
            state.days = Array.from({length: range.count}, (_, offset) => ({offset, closed: false,
                all_day: !from, slots: from ? [{start: from, end: until}] : []}));
            save(); render();
        });
        section.append(fill, message, days); editor.append(mode, section);
        const render = () => {
            section.hidden = state.mode !== 'per_day';
            days.replaceChildren();
            if (section.hidden) return;
            const range = dates();
            message.textContent = !range ? labels.invalid_dates
                : state.days.length !== range.count ? labels.range_changed : '';
            for (const day of state.days) {
                const title = range ? new Date(range.start.getTime() + day.offset * 86400000).toISOString().slice(0, 10)
                    : `${labels.day} ${day.offset + 1}`;
                const box = el('fieldset', undefined, 'jem-schedule-day');
                box.append(el('legend', title));
                const controls = el('div', undefined, 'jem-schedule-controls');
                for (const key of ['closed', 'all_day']) {
                    const label = el('label'); const input = el('input'); input.type = 'checkbox'; input.checked = day[key];
                    input.addEventListener('change', () => {
                        day[key] = input.checked;
                        if (input.checked) { day.slots = []; day[key === 'closed' ? 'all_day' : 'closed'] = false; }
                        save(); render();
                    });
                    label.append(input, document.createTextNode(labels[key])); controls.append(label);
                }
                box.append(controls);
                if (!day.closed && !day.all_day) {
                    day.slots.forEach((slot, index) => {
                        const row = el('div', undefined, 'jem-schedule-slot');
                        for (const key of ['start', 'end']) {
                            const label = el('label', labels[key]); const input = el('input');
                            input.type = 'time'; input.step = '1'; input.value = slot[key];
                            input.setAttribute('aria-label', `${title} ${labels[key]} ${index + 1}`);
                            input.addEventListener('change', () => { slot[key] = input.value; save(); });
                            label.append(input); row.append(label);
                        }
                        row.append(button(labels.remove, () => { day.slots.splice(index, 1); save(); render(); }));
                        box.append(row);
                    });
                    if (day.slots.length < 8) box.append(button(labels.add, () => {
                        day.slots.push({start: '', end: ''}); save(); render();
                    }));
                }
                days.append(box);
            }
        };
        mode.addEventListener('change', () => { state.mode = mode.value; save(); render(); });
        form.addEventListener('change', (event) => {
            if (['jform[dates]', 'jform[enddates]'].includes(event.target.name)) render();
        });
        render();
    };
    const boot = () => document.querySelectorAll('[data-jem-event-schedule]').forEach(initialise);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
    document.addEventListener('joomla:updated', boot);
})();
