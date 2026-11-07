/** Keep map menu defaults dependent on locations already stored in JEM venues. */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const rows = Joomla.getOptions('jem.map.locations', []);
    const fields = Joomla.getOptions('jem.map.location-fields', {});
    Object.entries(fields).forEach(function ([id, config]) {
        const select = document.getElementById(id);
        if (!select || !select.form) return;
        const form = select.form;
        const placeholder = select.options[0].textContent;

        /** Rebuild from existing venues, preserving saved defaults on initial load. */
        function rebuild(reset = true) {
            const country = form.elements.namedItem(config.country);
            const county = form.elements.namedItem(config.county);
            const region = form.elements.namedItem(config.region);
            const countryValue = country && country.value !== '0' ? country.value : '';
            const countyValue = config.level === 'city' && county ? county.value : '';
            const regionValue = config.level !== 'region' && region ? region.value : '';
            const selected = reset ? '' : select.value;
            const values = [...new Set(rows.filter(function (row) {
                return (!countryValue || row.country === countryValue)
                    && (!regionValue || row.region === regionValue)
                    && (!countyValue || row.county === countyValue);
            }).map(row => row[config.level]).filter(value => value.trim() !== ''))];
            if (selected) values.push(selected);
            values.sort((a, b) => a.localeCompare(b, undefined, {numeric: true, sensitivity: 'base'}));
            select.replaceChildren(new Option(placeholder, ''),
                ...[...new Set(values)].map(value => new Option(value, value)));
            select.value = selected;
        }

        form.addEventListener('change', event => {
            if (event.target.name === config.country
                || (config.level !== 'region' && event.target.name === config.region)
                || (config.level === 'city' && event.target.name === config.county)) rebuild();
        });
        rebuild(false);
    });
});
