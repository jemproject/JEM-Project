(function () {
    'use strict';

    function submit(form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function clearContactSelection(contacts) {
        Array.from(contacts.options).forEach(function (option) {
            option.selected = option.value === '';
        });
    }

    function normaliseContactSelection(contacts) {
        var hasContact = Array.from(contacts.selectedOptions).some(function (option) {
            return option.value !== '';
        });

        Array.from(contacts.options).forEach(function (option) {
            if (option.value === '') {
                option.selected = !hasContact;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-jem-event-filters]').forEach(function (container) {
            var form = container.closest('form');
            var category = container.querySelector('[data-jem-contact-category-filter]');
            var contacts = container.querySelector('#filter_contacts');
            var customFields = container.querySelectorAll('[data-jem-custom-filter]');
            var clearButtons = form ? form.querySelectorAll('[data-jem-main-filters-clear]') : [];

            if (!form) {
                return;
            }

            if (category) {
                category.addEventListener('change', function () {
                    if (contacts) {
                        clearContactSelection(contacts);
                    }

                    submit(form);
                });
            }

            if (contacts) {
                contacts.addEventListener('change', function () {
                    normaliseContactSelection(contacts);
                });
            }

            clearButtons.forEach(function (clear) {
                clear.addEventListener('click', function (event) {
                    event.preventDefault();

                    var search = form.querySelector('#filter_search');
                    var month = form.querySelector('#filter_month');

                    if (search) {
                        search.value = '';
                    }

                    if (month) {
                        month.value = '';
                    }

                    if (category) {
                        category.value = '0';
                    }

                    if (contacts) {
                        clearContactSelection(contacts);
                    }

                    customFields.forEach(function (field) {
                        field.value = '';
                    });

                    submit(form);
                });
            });
        });
    });
}());
