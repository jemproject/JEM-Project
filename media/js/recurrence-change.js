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
