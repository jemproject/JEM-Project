/** Validate the final total across capacity areas in frontend and attendee forms. */
(() => {
    'use strict';
    const validators = new WeakMap();
    const text = (key, quantity) => Joomla.Text._(key).replace(/%[ds]/g, String(quantity));
    const initialise = () => {
        document.querySelectorAll('[data-jem-area-quantities]').forEach(group => {
            const form = group.closest('form');
            const fields = [...group.querySelectorAll('.jem-capacity-area-quantity')];
            if (!form || !fields.length) return;
            const minimum = Number(group.dataset.minimum);
            const maximum = Number(group.dataset.maximum);
            const error = group.querySelector('[data-area-error]');
            const output = group.querySelector('[data-area-total]');
            let touched = false;
            const active = () => {
                const response = form.querySelector('[name="reg_check"]:checked');
                if (response) return response.value === '1';
                const status = form.querySelector('#reg_status');
                return status ? ['1', '2'].includes(status.value) : true;
            };
            const validate = (report = false) => {
                fields[0].setCustomValidity('');
                if (!active()) {
                    error.hidden = true;
                    return true;
                }
                let total = 0;
                let malformed = false;
                fields.forEach(field => {
                    const value = field.value.trim();
                    const quantity = value === '' ? 0 : Number(value);
                    if (field.validity.badInput || (value !== '' && !/^\d+$/.test(value))
                        || !Number.isSafeInteger(quantity) || quantity < 0 || quantity > 2147483647) malformed = true;
                    total += quantity;
                });
                if (total > 2147483647) malformed = true;
                let message = '';
                if (malformed) message = text('COM_JEM_CAPACITY_REGISTRATION_INVALID_QUANTITY');
                else if (total === 0) message = text('COM_JEM_CAPACITY_REGISTRATION_SELECTION_REQUIRED');
                else if (total < minimum) message = text('COM_JEM_CAPACITY_REGISTRATION_BELOW_MINIMUM', minimum);
                else if (maximum > 0 && total > maximum) message = text('COM_JEM_CAPACITY_REGISTRATION_ABOVE_MAXIMUM', maximum);
                output.textContent = text('COM_JEM_CAPACITY_REGISTRATION_TOTAL', malformed ? '—' : total);
                fields[0].setCustomValidity(message);
                error.textContent = message;
                error.hidden = !message || !(touched || report);
                const valid = !message && fields.every(field => field.validity.valid);
                if (report && !valid) {
                    fields.find(field => !field.validity.valid)?.reportValidity();
                }
                return valid;
            };
            form.addEventListener('input', event => { if (group.contains(event.target)) touched = true; validate(); });
            form.addEventListener('change', () => validate());
            form.addEventListener('invalid', event => {
                if (fields.includes(event.target)) { touched = true; validate(); }
            }, true);
            form.addEventListener('submit', event => {
                if (!validate(true)) event.preventDefault();
            });
            validators.set(form, validate);
            validate();
        });
    };
    window.JemAreaQuantities = {
        validate(form, report = false) { return validators.get(form)?.(report) ?? true; }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise);
    else initialise();
})();
