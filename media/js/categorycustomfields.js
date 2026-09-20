/**
 * Category-specific JEM and Joomla custom fields.
 *
 * @package JEM
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
(function () {
    'use strict';

    const toIntegerList = (values) => Array.from(new Set((values || [])
        .map((value) => Number.parseInt(value, 10))
        .filter((value) => Number.isInteger(value) && value > 0)));

    const initialiseCategoryEditor = (editor) => {
        const hidden = editor.querySelector('#jform_custom_fields');
        const jemFields = Array.from(editor.querySelectorAll('[data-jem-legacy-field-id]'));
        const joomlaFields = Array.from(editor.querySelectorAll('[data-jem-joomla-field-id]'));
        const joomlaGroups = Array.from(editor.querySelectorAll('[data-jem-joomla-group-id]'));

        if (!hidden) {
            return;
        }

        const synchroniseGroupControls = () => {
            const selectedGroups = new Set(joomlaGroups
                .filter((input) => input.checked)
                .map((input) => input.value));

            joomlaFields.forEach((input) => {
                const groupId = input.dataset.jemJoomlaFieldGroup || '';
                input.disabled = groupId !== '' && selectedGroups.has(groupId);
            });
        };

        const synchronise = () => {
            const selectedGroups = toIntegerList(joomlaGroups
                .filter((input) => input.checked)
                .map((input) => input.value));
            const selectedGroupLookup = new Set(selectedGroups.map(String));
            const selectedJoomlaFields = joomlaFields.filter((input) => {
                const groupId = input.dataset.jemJoomlaFieldGroup || '';

                return input.checked && !selectedGroupLookup.has(groupId);
            });

            hidden.value = JSON.stringify({
                mode: 'selection',
                jem_field_ids: toIntegerList(jemFields
                    .filter((input) => input.checked)
                    .map((input) => input.value)),
                joomla_field_ids: toIntegerList(selectedJoomlaFields.map((input) => input.value)),
                joomla_group_ids: selectedGroups,
            });
            synchroniseGroupControls();
        };

        editor.addEventListener('change', synchronise);
        synchroniseGroupControls();
    };

    const initialiseEventForm = (form) => {
        form.querySelectorAll('[data-jem-event-custom-fields]').forEach((container) => {
            const fields = container.querySelectorAll(
                '[data-jem-event-legacy-field-id], [data-jem-event-joomla-field-id]'
            );
            container.hidden = fields.length === 0;
        });

        let reloading = false;

        form.addEventListener('change', (event) => {
            if (reloading || !event.target || !event.target.matches('#jform_cats, [name="jform[cats][]"]')) {
                return;
            }

            reloading = true;

            if (window.Joomla && typeof window.Joomla.submitform === 'function') {
                window.Joomla.submitform('event.reload', form);
                return;
            }

            const task = form.querySelector('[name="task"]');

            if (task) {
                task.value = 'event.reload';
            }

            form.submit();
        });
    };

    const initialise = () => {
        document.querySelectorAll('[data-jem-category-custom-fields-editor]').forEach(initialiseCategoryEditor);
        document.querySelectorAll('form[data-jem-category-custom-fields-form]').forEach(initialiseEventForm);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
}());
