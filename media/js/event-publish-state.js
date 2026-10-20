(function () {
    'use strict';

    function initialise() {
        const categories = document.getElementById('jform_cats');
        const state = document.getElementById('jform_published');

        if (!categories || !state || !window.Joomla || typeof window.Joomla.getOptions !== 'function') {
            return;
        }

        const allowed = new Set(window.Joomla.getOptions('jem.eventPublishState', []).map(String));

        function updateState() {
            const selected = Array.from(categories.selectedOptions, option => option.value);
            const canPublish = selected.length > 0 && selected.every(categoryId => allowed.has(categoryId));

            state.disabled = !canPublish;

            if (!canPublish) {
                state.value = '0';
            }
        }

        categories.addEventListener('change', updateState);
        updateState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
}());
