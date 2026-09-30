(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-jem-image-repair]').forEach(function (button) {
            const panel = button.closest('[data-jem-image-repair-panel]');

            if (panel) {
                [panel.dataset.jemImageRepairSelectId, panel.dataset.jemImageRepairFileId]
                    .filter(Boolean)
                    .forEach(function (id) {
                        const input = document.getElementById(id);

                        if (input) {
                            input.addEventListener('change', function () {
                                panel.hidden = true;
                            });
                        }
                    });
            }

            button.addEventListener('click', async function () {
                const confirmation = button.dataset.jemImageRepairConfirm || '';

                if (confirmation && !window.confirm(confirmation)) {
                    return;
                }

                const status = panel ? panel.querySelector('[data-jem-image-repair-status]') : null;
                const originalLabel = button.textContent;
                const token = Joomla.getOptions('csrf.token', '');
                const data = new URLSearchParams({
                    id: button.dataset.jemImageRepairEvent || '',
                    field: button.dataset.jemImageRepairField || '',
                    expected: button.dataset.jemImageRepairExpected || ''
                });

                if (token) {
                    data.set(token, '1');
                }

                button.disabled = true;
                button.textContent = button.dataset.jemImageRepairWorking || originalLabel;

                try {
                    const response = await fetch(button.dataset.jemImageRepairUrl || '', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: data.toString()
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || '');
                    }

                    if (status) {
                        status.textContent = result.message || '';
                    }
                    if (panel) {
                        panel.classList.remove('alert-warning');
                        panel.classList.add('alert-success');
                    }

                    const preview = panel
                        ? panel.closest('.jem-admin-image-profile').querySelector('[data-jem-image-current]')
                        : null;
                    if (preview) {
                        const previewUrl = new URL(preview.src, document.baseURI);
                        previewUrl.searchParams.set('jem-image-repaired', String(Date.now()));
                        preview.src = previewUrl.toString();
                    }

                    button.remove();
                } catch (error) {
                    const fallback = panel ? panel.dataset.jemImageRepairError : '';

                    if (status) {
                        status.textContent = error.message || fallback;
                    }
                    button.disabled = false;
                    button.textContent = originalLabel;
                }
            });
        });
    });
})();
