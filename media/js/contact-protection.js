/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

(function () {
    'use strict';

    function decodePayload(value) {
        var encoded = String(value || '').split('').reverse().join('');
        var binary = window.atob(encoded);
        var bytes = Uint8Array.from(binary, function (character) {
            return character.charCodeAt(0);
        });

        return JSON.parse(new TextDecoder('utf-8').decode(bytes));
    }

    function revealTelephone(container) {
        var payload;

        try {
            payload = decodePayload(container.dataset.jemProtectedContact);
        } catch (error) {
            return;
        }

        if (!payload || typeof payload.label !== 'string' || typeof payload.target !== 'string'
            || !/^[0-9+*#,;]+$/.test(payload.target)) {
            return;
        }

        var link = document.createElement('a');
        link.href = 'tel:' + payload.target;
        link.textContent = payload.label;
        container.replaceChildren(link);
        container.removeAttribute('data-jem-protected-contact');
    }

    function initialise() {
        document.querySelectorAll('[data-jem-protected-contact]').forEach(revealTelephone);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
}());
