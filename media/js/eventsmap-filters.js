/**
 * Events Map filter navigation. Dates and ranges are resolved server-side.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.jem_eventsmap .jem-date-filter').forEach(function (form) {
        const date = form.querySelector('[name="jem_map_filter_date"]');
        const mode = () => form.querySelector('[name="jem_map_filter_mode"]:checked');
        const submit = () => form.requestSubmit();
        form.querySelectorAll('[name="jem_map_filter_mode"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!radio.checked) return;
                if (date && radio.value !== 'date') date.value = '';
                submit();
            });
        });
        if (date) {
            date.addEventListener('change', function () {
                if (!date.value || !date.checkValidity()) return;
                if (!mode() || !['week', 'month', 'year'].includes(mode().value)) {
                    form.querySelector('[name="jem_map_filter_mode"][value="date"]').checked = true;
                }
                submit();
            });
        }
        form.querySelectorAll('select.auto-submit').forEach(function (select) {
            select.addEventListener('change', function () {
                // A parent change must not submit a stale child from another location.
                const children = select.name === 'jem_map_filter_country'
                    ? ['county', 'city'] : (select.name === 'jem_map_filter_county' ? ['city'] : []);
                children.forEach(function (name) {
                    const child = form.querySelector('[name="jem_map_filter_' + name + '"]');
                    if (child) child.value = '';
                });
                submit();
            });
        });
    });
});
