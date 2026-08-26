(function () {
    'use strict';

    function announce(button, message) {
        var status = button.nextElementSibling;

        if (status && status.hasAttribute('data-jem-share-status')) {
            status.textContent = '';
            window.setTimeout(function () {
                status.textContent = message;
            }, 20);
        }
    }

    function legacyCopy(link) {
        var field = document.createElement('textarea');
        field.value = link;
        field.setAttribute('readonly', 'readonly');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();

        var copied = false;

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(field);

        return copied;
    }

    function manualCopy(button, link) {
        window.prompt(button.getAttribute('data-jem-share-prompt') || 'Copy this link:', link);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-jem-share-link]');

        if (!button) {
            return;
        }

        var link = button.getAttribute('data-jem-share-link');

        if (!link) {
            return;
        }

        event.preventDefault();

        var success = function () {
            announce(button, button.getAttribute('data-jem-share-success') || 'Link copied');
        };
        var fallback = function () {
            if (legacyCopy(link)) {
                success();
            } else {
                manualCopy(button, link);
            }
        };

        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(success).catch(fallback);
        } else {
            fallback();
        }
    });
}());
