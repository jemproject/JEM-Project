/**
 * Keep required image feedback aligned with the selected publication state.
 */
(function () {
    'use strict';

    function getOptions() {
        return window.Joomla && typeof window.Joomla.getOptions === 'function'
            ? window.Joomla.getOptions('jem.imagePublication', {})
            : {};
    }

    function findFirst(ids) {
        for (var index = 0; index < ids.length; index += 1) {
            var field = document.getElementById(ids[index]);
            if (field) {
                return field;
            }
        }

        return null;
    }

    function findContainer(field) {
        return field ? field.closest('.control-group, .form-group, .jem-editevent-image-field, .jem-event-image-field, .jem-venue-image-control, .jem-category-image-control, .jem-image-upload-row, li, .field-calendar') : null;
    }

    function isPublished(options) {
        var published = document.getElementById(options.publishId || 'jform_published');

        return !published || String(published.value) === '1';
    }

    function hasImage(rule) {
        var selected = findFirst(rule.selectionIds || []);
        var upload = rule.uploadId ? document.getElementById(rule.uploadId) : null;
        var remove = rule.removeId ? document.getElementById(rule.removeId) : null;
        var isRemoved = remove && String(remove.value) === '1';
        var hasSelected = selected && String(selected.value || '').trim() !== '' && !isRemoved;
        var hasUpload = upload && upload.files && upload.files.length > 0;

        return Boolean(hasSelected || hasUpload);
    }

    function decorate(rule, required, invalid, options) {
        var field = findFirst(rule.selectionIds || []) || (rule.uploadId ? document.getElementById(rule.uploadId) : null);
        var container = findContainer(field);
        if (!field || !container) {
            return;
        }

        field.setAttribute('aria-required', required ? 'true' : 'false');
        field.setAttribute('aria-invalid', invalid ? 'true' : 'false');
        container.classList.toggle('jem-image-required-publish', required);
        container.classList.toggle('jem-image-required-invalid', invalid);
        var visibleControl = container.querySelector('input:not([type="hidden"]), select, .choices__inner');
        if (visibleControl) {
            visibleControl.classList.toggle('is-invalid', invalid);
        }

        var note = container.querySelector('[data-jem-image-required-note="' + rule.profile + '"]');
        if (required && !note) {
            note = document.createElement('div');
            note.className = 'small ' + (invalid ? 'text-danger' : 'text-muted');
            note.setAttribute('data-jem-image-required-note', rule.profile);
            note.textContent = options.requiredText || 'Required to publish';
            container.appendChild(note);
        } else if (note) {
            note.classList.toggle('text-danger', invalid);
            note.classList.toggle('text-muted', !invalid);
            if (!required) {
                note.remove();
            }
        }
    }

    function validate(options, showErrors) {
        if (!isPublished(options)) {
            (options.rules || []).forEach(function (rule) {
                decorate(rule, false, false, options);
            });
            return true;
        }

        var firstInvalid = null;
        (options.rules || []).forEach(function (rule) {
            var valid = hasImage(rule);
            decorate(rule, true, !valid, options);
            if (!valid && !firstInvalid) {
                firstInvalid = findFirst(rule.selectionIds || []) || (rule.uploadId ? document.getElementById(rule.uploadId) : null);
            }
        });

        if (showErrors && firstInvalid) {
            var message = options.message || 'Add the required image before publishing.';
            if (window.Joomla && typeof window.Joomla.renderMessages === 'function') {
                window.Joomla.renderMessages({error: [message]});
            }
            var focusTarget = firstInvalid.closest('.control-group, .form-group, .jem-editevent-image-field, .jem-event-image-field, .jem-venue-image-control, .jem-category-image-control, .jem-image-upload-row, li') || firstInvalid;
            focusTarget.scrollIntoView({behavior: 'smooth', block: 'center'});
            if (typeof firstInvalid.focus === 'function' && firstInvalid.type !== 'hidden') {
                firstInvalid.focus({preventScroll: true});
            }
        }

        return !firstInvalid;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var options = getOptions();
        if (!Array.isArray(options.rules) || options.rules.length === 0) {
            return;
        }

        var form = document.getElementById('adminForm') || document.querySelector('form.form-validate');
        if (!form) {
            return;
        }

        validate(options, false);

        var published = document.getElementById(options.publishId || 'jform_published');
        if (published) {
            published.addEventListener('change', function () {
                validate(options, false);
            });
        }

        form.addEventListener('submit', function (event) {
            if (!validate(options, true)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    });
}());
