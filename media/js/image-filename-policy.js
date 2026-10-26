/**
 * Resolve JEM image filename collisions before the form is submitted.
 * The server repeats the check during save to cover concurrent uploads.
 */
(function () {
    'use strict';

    function initialise(control) {
        var fileInput = document.getElementById(control.dataset.jemImageFileId);
        var form = control.closest('form');
        var nameInput = control.querySelector('input[name^="jem_image_name["]');
        var actionInput = control.querySelector('select[name^="jem_image_conflict["]');
        var confirmation = control.querySelector('input[name^="jem_image_replace_confirm["]');
        var dialog = control.querySelector('.jem-image-collision-dialog');
        var message = control.querySelector('.jem-image-collision-message');
        var status = control.querySelector('.jem-image-name-status');
        var request = null;
        var timer = null;
        var serial = 0;
        var checkedName = '';
        var acceptedName = '';
        var renaming = false;

        if (!fileInput || !form || !nameInput || !actionInput || !dialog || !message || !status) {
            return;
        }

        function show(state, text) {
            control.dataset.jemImageCheckState = state;
            status.hidden = !text;
            status.classList.remove('alert-info', 'alert-success', 'alert-warning');
            if (text) {
                status.classList.add(state === 'available' ? 'alert-success' : (state === 'checking' ? 'alert-info' : 'alert-warning'));
                status.textContent = text;
            }
        }

        function closeDialog() {
            if (dialog.open) {
                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
            }
        }

        function openDialog() {
            if (!dialog.open) {
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }
            }
            actionInput.focus();
        }

        function clearChoice() {
            actionInput.value = '';
            acceptedName = '';
            if (confirmation) {
                confirmation.checked = false;
                confirmation.closest('label').hidden = true;
            }
        }

        function check(openOnConflict) {
            clearTimeout(timer);
            serial += 1;
            var current = serial;
            clearChoice();
            checkedName = '';

            if (request) {
                request.abort();
            }
            if (!fileInput.files || !fileInput.files.length) {
                closeDialog();
                show('', '');
                return;
            }

            request = new AbortController();
            show('checking', control.dataset.jemImageChecking);
            var body = new URLSearchParams();
            var pathInput = form.querySelector('input[name="jform[image_path]"]');
            var recordInput = form.querySelector('input[name="jform[id]"]');
            body.set(control.dataset.jemImageToken, '1');
            body.set('resource', control.dataset.jemImageResource);
            body.set('image_path', pathInput ? pathInput.value : control.dataset.jemImagePath);
            body.set('record_id', recordInput ? recordInput.value : control.dataset.jemImageRecordId);
            body.set('filename', fileInput.files[0].name);
            body.set('stem', nameInput.value.trim());
            if (control.dataset.jemImageResource === 'event' && !body.get('image_path')) {
                ['id', 'dates', 'created', 'alias', 'locid', 'type_id'].forEach(function (key) {
                    var input = form.querySelector('[name="jform[' + key + ']"]');
                    if (input) {
                        body.set('context[' + key + ']', input.value);
                    }
                });
                new FormData(form).getAll('jform[cats][]').forEach(function (categoryId) {
                    body.append('context[cats][]', categoryId);
                });
            }

            fetch(control.dataset.jemImageCheckUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
                body: body,
                signal: request.signal
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Filename check failed');
                }
                return response.json();
            }).then(function (result) {
                if (current !== serial) {
                    return;
                }
                if (!result.success || !result.data || typeof result.data.exists !== 'boolean') {
                    throw new Error('Invalid filename check response');
                }

                checkedName = result.data.filename;
                if (result.data.exists) {
                    message.textContent = control.dataset.jemImageConflict.replace('%s', checkedName);
                    show('conflict', openOnConflict ? '' : message.textContent);
                    if (openOnConflict) {
                        openDialog();
                    }
                } else {
                    closeDialog();
                    renaming = false;
                    show('available', control.dataset.jemImageAvailable.replace('%s', checkedName));
                }
            }).catch(function (error) {
                if (current !== serial || error.name === 'AbortError') {
                    return;
                }
                closeDialog();
                show('failed', control.dataset.jemImageCheckFailed);
            });
        }

        actionInput.addEventListener('change', function () {
            if (confirmation) {
                confirmation.checked = false;
                confirmation.closest('label').hidden = actionInput.value !== 'replace';
            }
        });
        dialog.querySelector('[data-jem-image-accept]').addEventListener('click', function () {
            if (!actionInput.value) {
                actionInput.focus();
                return;
            }
            if (actionInput.value === 'replace' && confirmation && !confirmation.checked) {
                confirmation.focus();
                return;
            }
            acceptedName = checkedName;
            closeDialog();
            show('resolved', actionInput.options[actionInput.selectedIndex].text + ': ' + checkedName);
        });
        dialog.querySelector('[data-jem-image-cancel]').addEventListener('click', function () {
            clearChoice();
            closeDialog();
            show('conflict', message.textContent);
            fileInput.focus();
        });
        dialog.querySelector('[data-jem-image-rename]').addEventListener('click', function () {
            clearChoice();
            renaming = true;
            closeDialog();
            show('conflict', message.textContent);
            nameInput.focus();
        });
        dialog.addEventListener('cancel', function () {
            clearChoice();
            show('conflict', message.textContent);
        });
        fileInput.addEventListener('change', function () {
            renaming = false;
            closeDialog();
            check(true);
        });
        nameInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                check(!renaming);
            }, 250);
        });
        nameInput.addEventListener('blur', function () {
            if (renaming && fileInput.files && fileInput.files.length) {
                renaming = false;
                check(true);
            }
        });
        form.addEventListener('change', function (event) {
            if (event.target === fileInput || event.target === nameInput || !fileInput.files.length) {
                return;
            }
            if (/^jform\[(image_path|dates|created|alias|locid|type_id|cats)\]/.test(event.target.name)) {
                closeDialog();
                check(true);
            }
        });
        form.addEventListener('submit', function (event) {
            if (control.dataset.jemImageCheckState === 'checking'
                || (control.dataset.jemImageCheckState === 'conflict' && acceptedName !== checkedName)) {
                event.preventDefault();
                if (control.dataset.jemImageCheckState === 'conflict') {
                    openDialog();
                }
            }
        });
    }

    function start() {
        document.querySelectorAll('.jem-image-filename-policy[data-jem-image-check-url]').forEach(initialise);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
