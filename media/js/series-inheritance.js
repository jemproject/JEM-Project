/** Recurrence controls follow the global inheritance choice; server validation is authoritative. */
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('jem-series-inherit');
    if (!toggle) return;
    const selector = '#jform_recurrence_type, [name="recurrence_number"], [name="recurrence_byday"], [name="recurrence_byday[]"], [name="recurrence_bylastday"], #jform_recurrence_limit_date';
    const controls = [...document.querySelectorAll(selector)];
    const sync = () => {
        document.querySelectorAll('[data-jem-inherited-value]').forEach(node => node.remove());
        controls.forEach(control => {
            control.disabled = toggle.checked;
            if (toggle.checked && control.name && (!['checkbox', 'radio'].includes(control.type) || control.checked)) {
                const mirror = document.createElement('input');
                mirror.type = 'hidden';
                mirror.name = control.name;
                mirror.value = control.value;
                mirror.dataset.jemInheritedValue = '1';
                control.after(mirror);
            }
        });
    };
    toggle.addEventListener('change', sync);
    sync();
});
