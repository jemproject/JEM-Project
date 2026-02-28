// Month input fallback for browsers without native support
// Keeps YYYY-MM format compatible with HTML5 validation
// Keeps native picker where available

document.addEventListener('DOMContentLoaded', function () {

    // Feature detection
    const test = document.createElement('input');
    test.setAttribute('type', 'month');

    // Browser supports month input → nothing to do
    if (test.type === 'month') {
        return; // Browser supports month input
    }

    const inputs = document.querySelectorAll('input[type="month"]');

    inputs.forEach(function (input) {

        input.type = 'text';
        input.style.display = 'none'; // hide original input

        const monthSelect = document.createElement('select');
        const yearSelect  = document.createElement('select');

        // Add Joomla Core class
        monthSelect.className = 'form-select';
        yearSelect.className  = 'form-select';

        // Current date
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = String(now.getMonth() + 1).padStart(2, '0');

        // Parse value from input if already set
        let selectedYear = '';
        let selectedMonth = '';
        if (input.value && /^\d{4}-\d{2}$/.test(input.value)) {
            [selectedYear, selectedMonth] = input.value.split('-');
        } else {
            input.value = ''; // clean up invalid value
        }

        // Year select: 5 years before and 50 years after current year as reasonable range
        // But allow unlimited selection via typing in input if needed
        for (let y = currentYear - 5; y <= currentYear + 50; y++) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            if (y.toString() === selectedYear) opt.selected = true;
            yearSelect.appendChild(opt);
        }
        // If no value selected, default to current year but don't auto-set input yet
        if (!selectedYear) yearSelect.value = currentYear;

        // Month select
        for (let m = 1; m <= 12; m++) {
            const opt = document.createElement('option');
            opt.value = String(m).padStart(2, '0');
            opt.textContent = String(m).padStart(2, '0');
            if (m.toString().padStart(2, '0') === selectedMonth) opt.selected = true;
            monthSelect.appendChild(opt);
        }
        if (!selectedMonth) monthSelect.value = currentMonth;

        // Always insert empty options at the top so the user can reset the selection
            const emptyOptYear = document.createElement('option');
            emptyOptYear.value = '';
            emptyOptYear.textContent = '----';
            yearSelect.insertBefore(emptyOptYear, yearSelect.firstChild);

            const emptyOptMonth = document.createElement('option');
            emptyOptMonth.value = '';
            emptyOptMonth.textContent = '--';
            monthSelect.insertBefore(emptyOptMonth, monthSelect.firstChild);

        // Select empty option when no value is set, otherwise restore the saved value
        if (!input.value) {
            yearSelect.value  = '';
            monthSelect.value = '';
        } else {
            input.value = selectedYear + '-' + selectedMonth;
        }

        function updateValue() {
            if (yearSelect.value && monthSelect.value) {
                input.value = yearSelect.value + '-' + monthSelect.value;
            } else {
                input.value = '';
            }
        }

        monthSelect.addEventListener('change', updateValue);
        yearSelect.addEventListener('change', updateValue);

        // Insert selects directly after the input
        input.parentNode.insertBefore(yearSelect, input.nextSibling);
        input.parentNode.insertBefore(monthSelect, yearSelect.nextSibling);
    });

});