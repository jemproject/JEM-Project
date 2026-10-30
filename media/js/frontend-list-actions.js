(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        const action = event.target.closest('.jem-status-link[data-jem-task]');

        if (!action) {
            return;
        }

        event.preventDefault();

        const form = action.closest('form');
        const task = action.dataset.jemTask;
        const checkbox = form && form.elements.namedItem(action.dataset.jemItemId);

        if (!form || !checkbox || checkbox.type !== 'checkbox' || checkbox.name !== 'cid[]'
            || checkbox.disabled || !/^(myevents|myvenues)\.(publish|unpublish)$/.test(task)
            || !window.Joomla || typeof window.Joomla.submitform !== 'function') {
            return;
        }

        // Unauthorised rows have no checkbox, so cb0, cb1, ... need not be consecutive.
        // A single-row action must clear every other selection in this form.
        form.querySelectorAll('input[type="checkbox"][name="cid[]"]').forEach(function (item) {
            item.checked = item === checkbox;
        });

        const boxchecked = form.elements.namedItem('boxchecked');

        if (boxchecked) {
            boxchecked.value = 1;
        }

        window.Joomla.submitform(task, form);
    });
}());
