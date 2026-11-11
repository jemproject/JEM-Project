/** JEM invitation composer. Server preview and send repeat every validation. */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('jem-invitation-form');
    if (!form) return;
    const audience = form.querySelector('#inv-audience');
    const refresh = () => {
        form.querySelectorAll('[data-inv-audience]').forEach(section => {
            section.hidden = section.dataset.invAudience !== audience.value;
            section.querySelectorAll('select').forEach(select => { select.disabled = section.hidden; });
        });
    };
    refresh();
    form.addEventListener('input', () => {
        form.querySelector('[data-inv-send]').disabled = true;
        refresh();
    });
    form.addEventListener('change', refresh);
});
