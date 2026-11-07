/** Preview and confirm a cancellation before the native JEM registration POST. */
(() => {
    'use strict';
    const initialise = () => {
        document.querySelectorAll('.jem-cancellation-dialog').forEach(dialog => {
            const form = dialog.closest('form');
            if (!form) return;
            const confirm = form.elements.namedItem('jem_cancel_confirm');
            const fingerprint = form.elements.namedItem('jem_cancel_fingerprint');
            const total = form.elements.namedItem('cancelplaces');
            const areaFields = form.querySelector('.jem-cancellation-areas');
            const bookingFields = form.querySelector('.jem-capacity-registration');
            const error = form.querySelector('[data-cancel-error]');
            const submit = form.querySelector('#jem_send_attend');
            let busy = false;
            let approved = false;
            let submitter = null;
            const isCancellation = () => form.querySelector('[name="reg_check"]:checked')?.value === '-1';
            const reset = () => { confirm.value = '0'; fingerprint.value = ''; approved = false; };
            const sync = () => {
                reset();
                const cancellation = isCancellation();
                if (areaFields) {
                    areaFields.hidden = !cancellation;
                    areaFields.disabled = !cancellation;
                    if (total) {
                        total.readOnly = cancellation;
                        if (cancellation) total.value = [...areaFields.querySelectorAll('[data-cancel-area]')]
                            .reduce((sum, input) => sum + (Number(input.value) || 0), 0);
                    }
                }
                if (bookingFields) {
                    bookingFields.hidden = cancellation;
                    bookingFields.querySelectorAll('input').forEach(input => { input.disabled = cancellation; });
                }
                // Only the series checkbox belonging to the selected action is submitted.
                form.querySelectorAll('[name="reg_check_series"]').forEach(input => {
                    input.disabled = input.dataset.registrationAction !== (cancellation ? '-1' : '1');
                });
            };
            form.addEventListener('input', sync);
            form.addEventListener('change', sync);
            const back = () => { reset(); busy = false; submit.disabled = false; submit.focus(); };
            dialog.addEventListener('cancel', back);
            dialog.querySelector('[data-cancel-back]').addEventListener('click', () => { dialog.close(); back(); });
            dialog.querySelector('[data-cancel-proceed]').addEventListener('click', () => {
                if (!fingerprint.value || approved) return;
                approved = true;
                confirm.value = '1';
                dialog.close();
                busy = false;
                submit.disabled = false;
                form.requestSubmit(submitter || submit);
            });
            form.addEventListener('submit', async event => {
                if (!isCancellation()) return;
                if (approved) {
                    if (busy) { event.preventDefault(); return; }
                    busy = true;
                    submit.disabled = true;
                    return;
                }
                event.preventDefault();
                if (busy) return;
                busy = true;
                submitter = event.submitter;
                submit.disabled = true;
                error.hidden = true;
                reset();
                try {
                    const body = new FormData(form);
                    body.set('task', 'event.cancellationPreview');
                    body.set('format', 'html');
                    const url = new URL(form.action, location.href);
                    url.searchParams.set('task', 'event.cancellationPreview');
                    url.searchParams.set('format', 'html');
                    const response = await fetch(url, {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}});
                    if (!response.ok) throw new Error(Joomla.Text._('COM_JEM_CANCELLATION_REVIEW_CHANGED'));
                    const payload = await response.json();
                    if (!payload.success) throw new Error(payload.message);
                    if (!payload.data.required) {
                        approved = true;
                        busy = false;
                        submit.disabled = false;
                        form.requestSubmit(submitter || submit);
                        return;
                    }
                    fingerprint.value = payload.data.fingerprint;
                    dialog.querySelector('[data-cancel-message]').textContent = payload.data.message;
                    const list = dialog.querySelector('[data-cancel-events]');
                    list.replaceChildren();
                    payload.data.events.forEach(item => {
                        const li = document.createElement('li');
                        const result = item.remaining === 0 ? Joomla.Text._('COM_JEM_CANCELLATION_FULL')
                            : Joomla.Text._('COM_JEM_CANCELLATION_REMAINING').replace('%s', item.remaining);
                        li.textContent = item.label + ': ' + result + (item.areas.length ? ' (' + item.areas.join(', ') + ')' : '');
                        list.append(li);
                    });
                    dialog.showModal();
                } catch (failure) {
                    back();
                    error.textContent = failure.message || Joomla.Text._('COM_JEM_CANCELLATION_REVIEW_CHANGED');
                    error.hidden = false;
                }
            });
            sync();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise);
    else initialise();
})();
