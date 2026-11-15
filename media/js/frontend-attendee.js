/** Validate quantities inline, then confirm a manager's reservation edit. */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.jem-edit-attendee form');
    if (!form) return;
    const error = form.querySelector('[data-reservation-error]');
    const quantities = [...form.querySelectorAll('[data-reservation-quantity]')];
    const validate = () => {
        const active = ['1', '2'].includes(form.elements.status.value);
        const invalid = active && quantities.length > 0 && quantities.reduce((sum, input) => sum + Number(input.value || 0), 0) < 1;
        if (error) {
            error.hidden = !invalid;
            error.textContent = invalid ? Joomla.Text._('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED') : '';
        }
        quantities.forEach(input => { input.classList.toggle('is-invalid', invalid); input.setAttribute('aria-invalid', String(invalid)); });
        return !invalid;
    };
    form.addEventListener('input', validate);
    form.addEventListener('change', validate);
    form.addEventListener('submit', event => {
        if (!validate()) { event.preventDefault(); error?.focus(); return; }
        if (!window.confirm(Joomla.Text._('COM_JEM_FE_RESERVATION_CONFIRM'))) event.preventDefault();
    });
});
