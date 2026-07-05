<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$countryGroups = $this->countryGroups ?? array();

if (!class_exists('JemHelperCountries')) {
    require_once JPATH_SITE . '/components/com_jem/helpers/countries.php';
}
?>

<div class="alert alert-info">
    <?php echo Text::_('COM_JEM_COUNTRIES_SETTINGS_DESC'); ?>
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
    <button type="button" class="btn btn-secondary btn-sm jem-country-toggle" data-target="all" data-state="1">
        <?php echo Text::_('COM_JEM_COUNTRIES_SELECT_ALL'); ?>
    </button>
    <button type="button" class="btn btn-secondary btn-sm jem-country-toggle" data-target="all" data-state="0">
        <?php echo Text::_('COM_JEM_COUNTRIES_UNSELECT_ALL'); ?>
    </button>
</div>

<style>
    .jem-country-tree {
        width: 100%;
    }

    .jem-country-node {
        border: 1px solid var(--border, #dfe3e7);
        border-radius: .25rem;
        background: var(--body-bg, #fff);
    }

    .jem-country-node + .jem-country-node {
        margin-top: .5rem;
    }

    .jem-country-summary {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .75rem 1rem;
        cursor: pointer;
        list-style: none;
    }

    .jem-country-summary::-webkit-details-marker {
        display: none;
    }

    .jem-country-summary::before {
        content: "+";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.4rem;
        height: 1.4rem;
        border: 1px solid currentColor;
        border-radius: .2rem;
        font-weight: 700;
        line-height: 1;
    }

    .jem-country-node[open] > .jem-country-summary::before {
        content: "-";
    }

    .jem-country-summary .form-check-input {
        margin-top: 0;
    }

    .jem-country-children {
        border-top: 1px solid var(--border, #dfe3e7);
        padding: 1rem 1rem 1rem 3.15rem;
    }

    .jem-country-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: .55rem 1rem;
    }

    .jem-country-grid .form-check {
        min-width: 0;
        margin-bottom: 0;
    }

    .jem-country-grid .form-check-label {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        min-width: 0;
    }

    .jem-country-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .jem-country-checkbox:not(:checked) + .form-check-label {
        color: var(--text-muted, #6c757d);
        font-weight: 400;
    }

    .jem-country-checkbox:checked + .form-check-label {
        font-weight: 700;
    }

    .jem-continent-label.is-inactive {
        color: var(--text-muted, #6c757d);
    }

    .jem-country-flag {
        width: 20px;
        height: 14px;
        object-fit: contain;
        vertical-align: -2px;
        margin-right: .4rem;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, .08);
    }
</style>

<input type="hidden" name="jem_country_selection" id="jem-country-selection" value="">

<div class="jem-country-tree" id="jem-country-groups">
    <?php foreach ($countryGroups as $continent => $group) : ?>
        <?php
        if (empty($group['countries'])) {
            continue;
        }

        $continentId = 'jem-country-continent-' . strtolower($continent);
        $continentInputId = $continentId . '-check';
        $isFullyActive = (int) $group['active'] === (int) $group['total'];
        $isPartial = (int) $group['active'] > 0 && (int) $group['active'] < (int) $group['total'];
        ?>
        <details class="jem-country-node" data-continent="<?php echo $this->escape($continent); ?>" <?php echo $isPartial ? 'open' : ''; ?>>
            <summary class="jem-country-summary">
                <input
                    type="checkbox"
                    class="form-check-input jem-continent-checkbox"
                    id="<?php echo $continentInputId; ?>"
                    data-continent="<?php echo $this->escape($continent); ?>"
                    <?php echo $isFullyActive ? 'checked' : ''; ?>
                >
                <span class="flex-grow-1">
                    <label class="fw-bold mb-0 jem-continent-label<?php echo (int) $group['active'] === 0 ? ' is-inactive' : ''; ?>" for="<?php echo $continentInputId; ?>" data-continent="<?php echo $this->escape($continent); ?>">
                        <?php echo $this->escape($group['label']); ?>
                    </label>
                    <span class="text-muted jem-country-count" data-continent="<?php echo $this->escape($continent); ?>">
                        <?php echo Text::sprintf('COM_JEM_COUNTRIES_ACTIVE_COUNT', (int) $group['active'], (int) $group['total']); ?>
                    </span>
                </span>
            </summary>
            <div class="jem-country-children" id="<?php echo $continentId; ?>">
                <div class="jem-country-grid">
                    <?php foreach ($group['countries'] as $country) : ?>
                        <?php
                        $fieldId = 'jem-country-' . strtolower($country->iso2);
                        $flagSrc = JemHelperCountries::getIsoFlag((string) $country->iso2);
                        ?>
                        <div>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input jem-country-checkbox"
                                    id="<?php echo $fieldId; ?>"
                                    value="1"
                                    data-country="<?php echo $this->escape($country->iso2); ?>"
                                    data-continent="<?php echo $this->escape($continent); ?>"
                                    <?php echo !empty($country->published) ? 'checked' : ''; ?>
                                >
                                <label class="form-check-label" for="<?php echo $fieldId; ?>">
                                    <?php if ($flagSrc) : ?>
                                        <img
                                            src="<?php echo $this->escape($flagSrc); ?>"
                                            alt=""
                                            class="jem-country-flag"
                                            loading="lazy"
                                        >
                                    <?php endif; ?>
                                    <span class="jem-country-name"><?php echo $this->escape($country->name); ?></span>
                                    <span class="text-muted">(<?php echo $this->escape($country->iso2); ?>)</span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    <?php endforeach; ?>
</div>

<script>
(function() {
    function updateCountrySelection() {
        var field = document.getElementById('jem-country-selection');
        var countries = Array.prototype.slice.call(document.querySelectorAll('.jem-country-checkbox'));

        if (!field || !countries.length) {
            return;
        }

        var grouped = {};
        var activeCount = 0;

        countries.forEach(function(input) {
            var continent = input.getAttribute('data-continent') || '';
            var country = input.getAttribute('data-country') || '';

            if (!continent || !country) {
                return;
            }

            if (!grouped[continent]) {
                grouped[continent] = [];
            }

            grouped[continent].push(input);

            if (input.checked) {
                activeCount++;
            }
        });

        if (activeCount === countries.length) {
            field.value = JSON.stringify({all: true, continents: [], include: []});
            return;
        }

        var selection = {
            all: false,
            continents: [],
            include: []
        };

        Object.keys(grouped).sort().forEach(function(continent) {
            var group = grouped[continent];
            var active = group.filter(function(input) {
                return input.checked;
            });

            if (!active.length) {
                return;
            }

            if (active.length === group.length) {
                selection.continents.push(continent);
                return;
            }

            active.forEach(function(input) {
                selection.include.push(input.getAttribute('data-country'));
            });
        });

        field.value = JSON.stringify(selection);
    }

    function updateContinent(continent) {
        var countries = Array.prototype.slice.call(document.querySelectorAll('.jem-country-checkbox[data-continent="' + continent + '"]'));
        var continentInput = document.querySelector('.jem-continent-checkbox[data-continent="' + continent + '"]');
        var continentLabel = document.querySelector('.jem-continent-label[data-continent="' + continent + '"]');
        var count = document.querySelector('.jem-country-count[data-continent="' + continent + '"]');

        if (!continentInput || !countries.length) {
            return;
        }

        var active = countries.filter(function(input) {
            return input.checked;
        }).length;

        continentInput.checked = active === countries.length;
        continentInput.indeterminate = active > 0 && active < countries.length;

        if (continentLabel) {
            continentLabel.classList.toggle('is-inactive', active === 0);
        }

        if (count) {
            count.textContent = '(' + active + '/' + countries.length + ' active)';
        }

        updateCountrySelection();
    }

    document.querySelectorAll('.jem-continent-checkbox').forEach(function(input) {
        updateContinent(input.getAttribute('data-continent'));

        input.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        input.addEventListener('change', function() {
            var continent = input.getAttribute('data-continent');
            var checked = input.checked;

            document.querySelectorAll('.jem-country-checkbox[data-continent="' + continent + '"]').forEach(function(country) {
                country.checked = checked;
            });

            updateContinent(continent);
        });
    });

    document.querySelectorAll('.jem-country-checkbox').forEach(function(input) {
        input.addEventListener('change', function() {
            updateContinent(input.getAttribute('data-continent'));
        });
    });

    document.addEventListener('click', function(event) {
        var button = event.target.closest('.jem-country-toggle');

        if (!button) {
            return;
        }

        var target = button.getAttribute('data-target');
        var checked = button.getAttribute('data-state') === '1';
        var selector = '.jem-country-checkbox';

        if (target !== 'all') {
            selector += '[data-continent="' + target + '"]';
        }

        document.querySelectorAll(selector).forEach(function(input) {
            input.checked = checked;
        });

        if (target === 'all') {
            document.querySelectorAll('.jem-continent-checkbox').forEach(function(input) {
                updateContinent(input.getAttribute('data-continent'));
            });
        } else {
            updateContinent(target);
        }
    });

    updateCountrySelection();
})();
</script>
