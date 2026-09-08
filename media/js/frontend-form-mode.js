(function () {
    'use strict';

    const advancedFieldSelector = '[data-jem-advanced-field]';
    const modeInputSelector = '[name="jform[attribs][frontend_form_mode]"]';

    function markAdvancedTabControls(form) {
        form.querySelectorAll('joomla-tab-element[data-jem-advanced-field]').forEach(function (tab) {
            form.querySelectorAll('button[aria-controls]').forEach(function (button) {
                if (button.getAttribute('aria-controls') === tab.id) {
                    button.setAttribute('data-jem-advanced-field', '');
                }
            });
        });
    }

    function activateTab(tab) {
        const tabSet = tab ? tab.closest('joomla-tab') : null;

        if (tabSet && typeof tabSet.activateTab === 'function') {
            tabSet.activateTab(tab, false);
        }
    }

    function leaveHiddenAdvancedTab(form) {
        const activeAdvancedTab = form.querySelector('joomla-tab-element[data-jem-advanced-field][active]');

        if (!activeAdvancedTab) {
            return;
        }

        const tabSet = activeAdvancedTab.closest('joomla-tab');
        const fallbackTab = tabSet
            ? Array.from(tabSet.children).find(function (tab) {
                return tab.matches('joomla-tab-element:not([data-jem-advanced-field])');
            })
            : null;

        if (fallbackTab) {
            activateTab(fallbackTab);
        }
    }

    function setAdvancedMode(form, toggle, enabled) {
        form.classList.toggle('jem-form-mode--advanced', enabled);
        toggle.classList.toggle('active', enabled);
        toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');

        const modeInput = form.querySelector(modeInputSelector);

        if (modeInput) {
            modeInput.value = enabled ? 'advanced' : 'easy';
        }

        if (!enabled) {
            leaveHiddenAdvancedTab(form);
        }
    }

    function initialiseForm(form) {
        const toggle = form.querySelector('[data-jem-form-mode-toggle]');

        if (!toggle || !form.querySelector(advancedFieldSelector)) {
            return;
        }

        const modeInput = form.querySelector(modeInputSelector);
        const initialModeIsAdvanced = modeInput && modeInput.value === 'advanced';

        markAdvancedTabControls(form);
        form.classList.add('jem-form-mode--ready');
        setAdvancedMode(form, toggle, initialModeIsAdvanced);

        if (window.customElements && typeof window.customElements.whenDefined === 'function') {
            window.customElements.whenDefined('joomla-tab').then(function () {
                markAdvancedTabControls(form);
                setAdvancedMode(form, toggle, toggle.getAttribute('aria-pressed') === 'true');
            });
        }

        toggle.addEventListener('click', function () {
            setAdvancedMode(form, toggle, toggle.getAttribute('aria-pressed') !== 'true');
        });

        form.addEventListener('invalid', function (event) {
            if (event.target instanceof Element && event.target.closest(advancedFieldSelector)) {
                setAdvancedMode(form, toggle, true);

                const advancedTab = event.target.closest('joomla-tab-element[data-jem-advanced-field]');

                if (advancedTab) {
                    activateTab(advancedTab);
                }
            }
        }, true);

        if (form.querySelector(
            advancedFieldSelector + '.invalid, '
            + advancedFieldSelector + ' .invalid, '
            + advancedFieldSelector + '[aria-invalid="true"], '
            + advancedFieldSelector + ' [aria-invalid="true"]'
        )) {
            setAdvancedMode(form, toggle, true);
        }
    }

    function initialise() {
        document.querySelectorAll('[data-jem-form-mode]').forEach(initialiseForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
}());
