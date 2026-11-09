/** Confirm recurrence replacement before submitting either JEM event editor. */
(() => {
    'use strict';
    const initialise = () => {
        const dialog = document.querySelector('.jem-recurrence-change');
        if (!dialog || !window.Joomla || !Joomla.submitform) return;
        const form = dialog.closest('form');
        const original = Joomla.submitform;
        let submitting = false;
        const submitOriginal = function (...args) {
            submitting = true;
            try { return original.apply(this, args); } finally { submitting = false; }
        };
        let pending = false;
        let resume = null;
        const choice = form.elements.namedItem('jem_recurrence_choice');
        const fingerprint = form.elements.namedItem('jem_recurrence_fingerprint');
        const proceed = dialog.querySelector('[data-recurrence-continue]');
        const cancel = () => {
            choice.value = '';
            fingerprint.value = '';
            pending = false;
            resume = null;
        };
        dialog.addEventListener('cancel', cancel);
        dialog.querySelector('[data-recurrence-cancel]').addEventListener('click', () => {
            cancel();
            dialog.close();
        });
        dialog.addEventListener('change', () => {
            proceed.disabled = !dialog.querySelector('input[type="radio"]:checked:not(:disabled)');
        });
        proceed.addEventListener('click', () => {
            const selected = dialog.querySelector('input[type="radio"]:checked:not(:disabled)');
            if (!selected || !resume) return;
            choice.value = selected.value;
            dialog.close();
            const submit = resume;
            resume = null;
            submit();
        });
        /**
         * Send Gregorian preview dates independently of the AJAX request language.
         * @param {FormData} body Scalar preview data; the visible fields remain unchanged.
         * @returns {void} Legacy calendars keep their existing localised transport.
         * @throws {Error} When the displayed date cannot be parsed without rollover.
         */
        const normalisePreviewDates = body => {
            const dates = [];
            for (const name of ['dates', 'enddates', 'recurrence_limit_date']) {
                const key = 'jform[' + name + ']';
                if (!body.has(key)) continue;
                const field = form.elements.namedItem(key);
                const wrapper = field && field.closest && field.closest('.field-calendar');
                const calendar = wrapper && wrapper._joomlaCalendar;
                const value = String(body.get(key)).trim();
                if (value === '') {
                    dates.push([key, '']);
                    continue;
                }
                // Do not guess the format of an override or a non-Gregorian calendar.
                if (!calendar || calendar.params.dateType !== 'gregorian'
                    || typeof Date.parseFieldDate !== 'function') return;
                const parsed = Date.parseFieldDate(value, calendar.params.dateFormat,
                    calendar.params.dateType, calendar.strings, true);
                if (!parsed || parsed.print(calendar.params.dateFormat, calendar.params.dateType,
                    true, calendar.strings) !== value) {
                    throw new Error(field.getAttribute('data-validation-text')
                        || Joomla.Text._('COM_JEM_RECURRENCE_CHANGE_REVIEW'));
                }
                // Calendar arithmetic uses the displayed day, without a UTC conversion.
                dates.push([key, parsed.print('%Y-%m-%d', 'gregorian', false, calendar.strings)]);
            }
            dates.forEach(([key, value]) => body.set(key, value));
            body.set('jem_recurrence_date_format', 'iso');
        };
        Joomla.submitform = function (task, target, ...args) {
            target = target || document.getElementById('adminForm');
            const id = form.elements.namedItem('jem_recurrence_event_id');
            if (target !== form || !id || !Number(id.value)
                || !['event.save', 'event.apply', 'event.save2new'].includes(task)) {
                return submitOriginal.call(this, task, target, ...args);
            }
            if (pending) return false;
            pending = true;
            choice.value = '';
            fingerprint.value = '';
            const body = new FormData();
            // Preview sends scalar form data, never uploads or editor file contents.
            for (const [key, value] of new FormData(form)) {
                if (typeof value === 'string') body.append(key, value);
            }
            try {
                normalisePreviewDates(body);
            } catch (error) {
                cancel();
                window.alert(error.message || Joomla.Text._('COM_JEM_RECURRENCE_CHANGE_REVIEW'));
                return false;
            }
            body.set('task', 'event.recurrencePreview');
            body.set('jform[id]', id.value);
            body.set('format', 'html');
            const url = new URL(form.action, window.location.href);
            url.searchParams.set('task', 'event.recurrencePreview');
            url.searchParams.set('format', 'html');
            fetch(url, {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}})
                .then(response => { if (!response.ok) throw new Error(Joomla.Text._('COM_JEM_RECURRENCE_CHANGE_REVIEW')); return response.json(); })
                .then(response => {
                    if (!response.success) throw new Error(response.message);
                    const plan = response.data;
                    const submit = () => submitOriginal.call(this, task, target, ...args);
                    if (!plan.required) { submit(); return; }
                    fingerprint.value = plan.fingerprint;
                    dialog.querySelector('[data-recurrence-keep-label]').textContent = plan.keepLabel;
                    dialog.querySelector('[data-recurrence-message]').textContent = plan.message;
                    dialog.querySelector('[data-recurrence-blocked]').textContent = plan.blocked;
                    dialog.querySelector('[data-recurrence-delete]').hidden = !plan.count;
                    for (const radio of dialog.querySelectorAll('input[type="radio"]')) {
                        radio.checked = false;
                        radio.disabled = radio.value === 'keep' ? !plan.keepAllowed : !plan.deleteAllowed;
                    }
                    proceed.disabled = true;
                    resume = submit;
                    dialog.showModal();
                })
                .catch(error => { cancel(); window.alert(error.message || Joomla.Text._('COM_JEM_RECURRENCE_CHANGE_REVIEW')); });
            return false;
        };
        // Stop native submit/Enter from bypassing the same confirmation path.
        form.addEventListener('submit', event => {
            if (submitting) return;
            event.preventDefault();
            Joomla.submitbutton('event.save');
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise);
    else initialise();
})();
