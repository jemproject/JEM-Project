/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

/**
 * Manages adding, removing, clearing, and ordering event attachments.
 */
(() => {
    'use strict';

    document.addEventListener('change', (event) => {
        if (isElement(event.target) && event.target.matches('.attach-field')) {
            updateAttachmentOrdering(getAttachmentsContainer(event.target));
        }
    });

    document.addEventListener('click', (event) => {
        if (!isElement(event.target)) {
            return;
        }

        const addButton = event.target.closest('.attachment-add');
        if (addButton) {
            event.preventDefault();
            appendAttachmentRow(getAttachmentsContainer(addButton));
            return;
        }

        const clearButton = event.target.closest('.clear-attach-field');
        if (clearButton) {
            event.preventDefault();
            clearAttachmentRow(clearButton.closest('tr, .jem-attachment-card'));
            return;
        }

        const removeRowButton = event.target.closest('.attachment-remove-row');
        if (removeRowButton) {
            event.preventDefault();
            removeAttachmentRow(removeRowButton);
            return;
        }

        const moveUpButton = event.target.closest('.attachment-move-up');
        if (moveUpButton) {
            event.preventDefault();
            moveAttachmentRow(moveUpButton, -1);
            return;
        }

        const moveDownButton = event.target.closest('.attachment-move-down');
        if (moveDownButton) {
            event.preventDefault();
            moveAttachmentRow(moveDownButton, 1);
            return;
        }

        const removeAttachmentButton = event.target.closest('.attach-remove');
        if (removeAttachmentButton) {
            event.preventDefault();
            removeStoredAttachment(removeAttachmentButton);
        }
    });

    function isElement(target) {
        return target instanceof Element;
    }

    function getAttachmentsContainer(element) {
        return element?.closest('.jem-attachments-tab')?.querySelector('#el-attachments tbody') ?? null;
    }

    function appendAttachmentRow(container) {
        const templates = container?.querySelectorAll('tr.jem-attachment-template-row');
        const template = templates?.length ? templates[templates.length - 1] : null;

        if (!template) {
            return;
        }

        const row = template.cloneNode(true);
        row.classList.remove('jem-attachment-template-row', 'd-none', 'hidden');
        row.hidden = false;
        row.setAttribute('aria-hidden', 'false');

        row.querySelectorAll('input, select, textarea, button').forEach((field) => {
            field.disabled = false;
        });

        row.querySelectorAll('.attach-field, .attach-name, .attach-desc, .attachment-order').forEach((field) => {
            field.value = '';
        });

        const published = row.querySelector('.attachment-published');
        if (published) {
            published.value = '1';
        }

        container.insertBefore(row, template);
        updateAttachmentOrdering(container);
    }

    function removeAttachmentRow(button) {
        const row = button.closest('tr, .jem-attachment-card');
        const container = getAttachmentsContainer(button);

        row?.remove();
        updateAttachmentOrdering(container);
    }

    function moveAttachmentRow(button, direction) {
        const row = button.closest('tr, .jem-attachment-card');
        const container = getAttachmentsContainer(button);

        if (!row || !container) {
            return;
        }

        const rows = getAttachmentRows(container);
        const index = rows.indexOf(row);

        if (direction < 0 && index > 0) {
            container.insertBefore(row, rows[index - 1]);
        } else if (direction > 0 && index >= 0 && index < rows.length - 1) {
            container.insertBefore(rows[index + 1], row);
        }

        updateAttachmentOrdering(container);
    }

    function getAttachmentRows(container) {
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll(
            'tr:not(.jem-attachment-template-row), .jem-attachment-card:not(.jem-attachment-template-row)'
        ));
    }

    function updateAttachmentOrdering(container) {
        getAttachmentRows(container).forEach((row, index) => {
            const ordering = row.querySelector('.attachment-order');
            if (ordering) {
                ordering.value = index;
            }
        });
    }

    function clearAttachmentRow(row) {
        if (!row) {
            return;
        }

        row.querySelectorAll('.attach-field, .attach-name, .attach-desc').forEach((field) => {
            field.value = '';
        });
    }

    async function removeStoredAttachment(button) {
        const match = button.id.match(/^attach-remove(\d+)(?::(.+))?$/);

        if (!match) {
            button.style.cursor = 'not-allowed';
            return;
        }

        const [, id, token] = match;
        const tokenQuery = token ? `&${encodeURIComponent(token)}=1` : '';
        const url = `index.php?option=com_jem&task=ajaxattachremove&format=raw&id=${encodeURIComponent(id)}${tokenQuery}`;

        button.style.cursor = 'wait';

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin'
            });
            const result = await response.text();

            if (response.ok && result.trim() === '1') {
                const container = getAttachmentsContainer(button);
                button.closest('tr, .jem-attachment-card')?.remove();
                updateAttachmentOrdering(container);
                return;
            }
        } catch (error) {
            // The cursor below communicates that the server request failed.
        }

        button.style.cursor = 'not-allowed';
    }
})();
