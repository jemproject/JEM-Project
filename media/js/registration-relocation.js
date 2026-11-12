/** Reviewed administrative reservation changes. Server recalculates all amounts and availability. */
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('jem-relocation');
    if (!box) return;
    const form = document.getElementById('adminForm');
    document.getElementById('jem-relocation-notify')?.addEventListener('click', () => {
        form.querySelector('input[name=task]').value = 'attendee.relocationNotify';
        form.submit();
    });
    const panel = document.getElementById('jem-relocation-panel');
    const error = document.getElementById('jem-relocation-error');
    const preview = document.getElementById('jem-relocation-preview');
    const confirm = document.getElementById('jem-relocation-confirm');
    const output = document.getElementById('jem-relocation-review');
    const choice = document.getElementById('jem-relocation-choice');
    const tr = key => Joomla.Text._('COM_JEM_RELOCATION_' + key);
    let token = '', busy = false, generation = 0;
    const invalidate = () => {
        ++generation;
        token = '';
        confirm.disabled = true;
        output.hidden = true;
        document.getElementById('jem-relocation-accept-label').hidden = !['agreement', 'consolidation_refund'].includes(choice.value);
    };
    document.getElementById('jem-relocation-open').addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        invalidate();
    });
    form.addEventListener('input', invalidate);
    form.addEventListener('change', invalidate);
    const amount = (cents, currency) => (cents / 100).toFixed(2) + (currency ? ' ' + currency : '');
    const showReview = review => {
        output.replaceChildren();
        const table = document.createElement('table');
        table.className = 'table table-striped';
        const head = table.createTHead().insertRow();
        ['AREA', 'BEFORE', 'AFTER'].forEach(key => { const th = document.createElement('th'); th.textContent = tr(key); head.append(th); });
        const body = table.createTBody();
        review.before.forEach((area, index) => {
            const next = review.after[index].quantity;
            if (!area.quantity && !next) return;
            const row = body.insertRow();
            [area.label, area.quantity, next].forEach(value => { row.insertCell().textContent = String(value); });
        });
        output.append(table);
        if (review.currency) {
            const list = document.createElement('dl'); list.className = 'row';
            ['old', 'quoted', 'difference', 'adjustment', 'total', 'paid', 'due', 'refund'].forEach(key => {
                const label = document.createElement('dt'); label.className = 'col-sm-6'; label.textContent = tr(key.toUpperCase());
                const value = document.createElement('dd'); value.className = 'col-sm-6'; value.textContent = amount(review.financial[key], review.currency);
                list.append(label, value);
            });
            output.append(list);
        }
        const note = document.createElement('p'); note.textContent = tr('CONFIRM_NOTICE'); output.append(note);
        for (const option of choice.options) {
            if (option.value === 'standard') continue;
            const supplement = ['promotion', 'consolidation_supplement'].includes(option.value);
            option.hidden = supplement ? review.financial.difference <= 0
                : review.financial.difference >= 0 || review.financial.paid <= review.financial.quoted;
        }
        output.hidden = false;
    };
    const request = async commit => {
        if (busy) return;
        busy = true; preview.disabled = true; confirm.disabled = true; error.hidden = true;
        const data = new FormData(form);
        const requestedGeneration = generation;
        data.set('task', commit ? 'attendee.relocationConfirm' : 'attendee.relocationPreview');
        data.set('format', 'json');
        if (commit) data.set('relocation_token', token);
        try {
            const response = await fetch('index.php?option=com_jem&format=json', { method: 'POST', body: data, credentials: 'same-origin' });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || tr('FAILED'));
            if (commit) { window.location.reload(); return; }
            if (generation !== requestedGeneration) return;
            token = result.data.token;
            showReview(result.data.review);
            confirm.disabled = false;
        } catch (failure) {
            token = ''; output.hidden = true;
            error.textContent = failure.message; error.hidden = false;
        } finally { busy = false; preview.disabled = false; }
    };
    preview.addEventListener('click', () => request(false));
    confirm.addEventListener('click', () => { if (token) request(true); });
});
